<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Runner de MERGE del Circuito (#334 F0-fix). Ejecuta el merge REAL de las ramas de item a `main`.
 *
 * POR QUÉ existe: la Torre corre como www-data (php-fpm), que NO puede escribir `.git` (objetos/refs
 * los creó el ejecutor=meganet, sin group-write para www-data) → un merge desde la Torre fallaba en
 * SILENCIO. Solución: la Torre ENCOLA (RoadmapCircuitoService::enqueueMerge) y ESTE runner, corriendo
 * on-box como meganet, drena la cola.
 *
 * #9990644 (Fase A de #9990640) — el runner YA NO corre en /var/www/megaisp (`base_path()`), el
 * checkout COMPARTIDO donde Irving o cualquier sesión humana pueden tener una rama de trabajo
 * checada a mano: un `git checkout main` ahí les movía el HEAD por debajo (incidente 2026-09-08).
 * Ahora corre en un worktree DEDICADO y EXCLUSIVO (`workDir()` = MERGE_WORKTREE, aprovisionado con
 * `circuito:provision-worktree`, mismo patrón que los wt-K de las terminales) donde NADA MÁS
 * escribe jamás. Ahí el merge SIEMPRE ocurre con HEAD detached en el tip de `main` (nunca lo
 * "adopta" como rama propia — `main` sigue atado a /var/www/megaisp, git no permite la misma rama
 * checada en dos worktrees), y al aterrizar bien se avanza `refs/heads/main` con `update-ref`
 * (operación de plumbing que SÍ puede mover una rama aunque esté checada en otro worktree —
 * `git branch -f` NO puede, lo bloquea; verificado empíricamente antes de escribir esto). Tras
 * avanzar main, `syncCheckoutPrincipal()` intenta reflejarlo en /var/www/megaisp de forma
 * BEST-EFFORT y SIN TOCAR nada si ese checkout no está en `main` o tiene cambios sin commitear
 * (así una rama ajena checada ahí nunca se toca, ni se pierde trabajo sin commitear).
 *
 * Garantías (lo que pidió Irving):
 *  - Corre en su worktree dedicado y exclusivo — resuelto por `workDir()` (ver arriba, #9990644).
 *  - SERIALIZADO: flock('merge.lock') → un merge a la vez, aunque lo llamen varios pickers.
 *  - Verificación de REGRESIÓN antes de aplicar: merge en 2 fases (--no-commit → verifica → commit).
 *  - Fallo (conflicto/regresión/permiso) → ABORTA, deja main intacto, escala el item a requiere_irving
 *    y GUARDA el error (mergeResult) para que la Torre lo MUESTRE. Nunca silencioso.
 *  - Kill switch (#9990640/#9990643, 2026-09-09 — REVIERTE la decisión previa de que "los merges
 *    se drenan siempre"): `drain()` respeta `isPaused()` DENTRO de sí mismo, justo tras tomar el
 *    flock. Antes solo `SchedulerCommand` chequeaba la pausa antes de llamar a `drain()`; el
 *    escape-hatch manual `circuito:merge-run` (`MergeRunCommand`) llamaba a `drain()` directo sin
 *    checar nada, así que con el freno puesto un operador podía seguir mergeando a mano — pasó de
 *    verdad en el incidente del 2026-09-08. Con el guard adentro, CUALQUIER caller (presente o
 *    futuro) queda protegido sin depender de que recuerde el chequeo.
 */
class MergeRunner
{
    protected const LOCK = '/home/meganet/circuito/merge.lock';

    /**
     * #9990466 — candado DEDICADO del rebuild post-merge (distinto de `LOCK` de arriba, que sólo
     * serializa el DRAIN de merges, y distinto del semáforo de `deploy/circuito/npm-build.sh`, que
     * limita cuántos `npm run` corren a la vez en el box). Evita que dos disparos de rebuild
     * detached se pisen y dejen el manifest a medio escribir; no bloquea el despacho porque nadie
     * más lo toma.
     */
    protected const BUILD_LOCK = '/home/meganet/circuito/rebuild-post-merge.lock';

    /**
     * #9990644 — worktree DEDICADO y EXCLUSIVO del runner (nunca /var/www/megaisp compartido).
     * Aprovisionado (idempotente) con `circuito:provision-worktree --path=... --base=main`, mismo
     * mecanismo que los wt-K de las terminales del circuito (detached, vendor copiado, .env/
     * node_modules symlinkeados). Ruta decidida por Irving (item #9990644, pregunta q3).
     */
    protected const MERGE_WORKTREE = '/home/meganet/worktrees/merge-runner';

    public function __construct(
        private RoadmapCircuitoService $svc,
        private JarvisIndiceService $jarvisIndice,
    ) {
    }

    /**
     * Drena la cola de merge (serializado por flock). Devuelve la lista de resultados.
     *
     * Distingue (#9990294) "cola vacía" de "no pude tomar el lock": si el lock está ocupado
     * (otro drain corriendo) devuelve NULL sin bloquear; si sí tomó el lock y no había nada
     * que mergear, devuelve []. Ambos casos siguen siendo falsy, así que cualquier consumidor
     * que solo haga `if (!$res)` sigue funcionando igual sin cambios.
     *
     * #9990643 — con el freno puesto (isPaused()) devuelve [] SIN mergear nada: es "cola vacía"
     * desde el punto de vista del caller (tomó el lock, no hizo nada), NO "no pude tomar el lock"
     * (null tiene otro significado, ver arriba). Los items que sigan en la cola de
     * RoadmapCircuitoService se quedan tal cual, listos para el próximo drain sin freno.
     */
    public function drain(): ?array
    {
        @mkdir(dirname(self::LOCK), 0775, true);
        $lock = @fopen(self::LOCK, 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return null; // no se pudo tomar el lock: ya hay un drain en curso
        }

        if ($this->svc->isPaused()) {
            flock($lock, LOCK_UN);
            fclose($lock);

            return [];
        }

        $out = [];
        $necesitaRebuild = false;
        try {
            while (($req = $this->svc->dequeueMerge()) !== null) {
                $itemId = (int) ($req['item_id'] ?? 0);
                if ($itemId <= 0) {
                    continue;
                }
                $item = RoadmapItem::find($itemId);
                if (! $item) {
                    $this->svc->recordMergeResult($itemId, $this->fail('Item no encontrado.', false));
                    continue;
                }
                $res = $this->performMerge($item, $req);
                if (! empty($res['necesita_rebuild'])) {
                    $necesitaRebuild = true;
                }
                // Escala a la bandeja de Irving si el merge falló y es escalable (conflicto/regresión).
                if (! empty($res['escalado'])) {
                    $item->estado_aprobacion = 'requiere_irving';
                    $log = $item->log ?: [];
                    $log[] = ['ts' => now()->toIso8601String(), 'por' => 'merge-runner', 'evento' => 'merge_escalado',
                        'branch' => $item->branch, 'motivo' => mb_substr((string) ($res['salida'] ?? ''), 0, 300)];
                    $item->log = $log;
                    $item->save();
                    Log::channel('roadmap_externo')->warning('merge-escalado', ['item' => $itemId, 'motivo' => $res['salida'] ?? '']);
                }
                $this->svc->recordMergeResult($itemId, $res);
                $out[] = ['item_id' => $itemId] + $res;
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        // #9990466 — COALESCER + SACAR DEL LOCK: el rebuild del bundle se dispara UNA SOLA VEZ por
        // drain completo (no una por merge) y AQUÍ, ya con `merge.lock` liberado (arriba) y sin que
        // el llamador (SchedulerCommand, que sostiene `scheduler.lock` durante todo `drain()`)
        // tenga que esperar los ~4min de `npm run prod`: `triggerRebuildAsync()` lanza el build
        // DETACHED y regresa de inmediato.
        if ($necesitaRebuild) {
            $this->triggerRebuildAsync();
        }

        return $out;
    }

    /**
     * Merge REAL de UNA rama a main (checkout principal, como meganet). 2 fases con verificación.
     * NUNCA hace push ni toca prod.
     */
    public function performMerge(RoadmapItem $item, array $req = []): array
    {
        $branch = (string) ($item->branch ?? '');
        if ($branch === '') {
            return $this->fail("El item #{$item->id} no tiene rama registrada.", true);
        }

        // ¿Existe la rama localmente?
        if (! $this->git(['rev-parse', '--verify', $branch])->isSuccessful()) {
            return $this->fail("La rama {$branch} no existe localmente.", true);
        }

        // Ya integrada (rama es ancestro de main) → no-op idempotente.
        if ($this->git(['merge-base', '--is-ancestor', $branch, 'main'])->isSuccessful()) {
            $sha = trim($this->git(['rev-parse', 'HEAD'])->getOutput());
            $this->markMerged($item, $item->merge_commit ?: $sha, $branch);

            return ['estado' => 'ok', 'ok' => true, 'merge_commit' => $item->merge_commit ?: $sha,
                'salida' => "La rama {$branch} ya estaba integrada en main.", 'escalado' => false, 'at' => time()];
        }

        // El árbol principal no debe tener cambios sin commitear QUE ESTE MERGE VAYA A TOCAR.
        //
        // ANTES exigía el árbol COMPLETAMENTE limpio, y eso volvía el circuito un embudo: basta un
        // solo archivo trackeado sucio —ajeno al merge— para que TODA rama terminada rebote a la
        // bandeja de Irving como `requiere_irving`. Pasó de verdad y en grande: el capturador de
        // decisiones (PerfilAprendizajeService) le escribe a `docs/pendientes-perfil-irving.md` en
        // cada decisión de Irving sin commitearlo nunca, y un `chmod` recursivo con
        // `core.fileMode=true` dejó 7.3k modificaciones fantasma. Resultado: días de merges
        // rechazados y trabajo YA HECHO parado en la bandeja por un motivo que nada tenía que ver
        // con el item.
        //
        // El guard real es la INTERSECCIÓN: solo importa lo sucio que el merge también toca (ahí sí
        // se perdería/pisaría trabajo no commiteado). Lo sucio ajeno al footprint no corre peligro.
        // Fail-closed: si no se puede calcular el footprint, se conserva el criterio conservador.
        if ($sucio = $this->sucioEnConflictoCon($branch)) {
            return $this->fail(
                "El checkout principal tiene cambios sin commitear en archivos que ESTE merge toca "
                . "({$sucio}). Commitea o descarta esos cambios y reintenta; mergear encima "
                . 'los perdería.',
                true
            );
        }

        // #9990644 — Asegura estar en el tip de main, SIN adoptar la rama (`main` sigue atada a
        // /var/www/megaisp; git no permite la misma rama checada en dos worktrees a la vez). El
        // worktree dedicado siempre trabaja con HEAD detached: al final, si todo aterriza bien, se
        // avanza `refs/heads/main` explícitamente con `update-ref` (ver más abajo).
        if (! $this->git(['checkout', '--detach', 'main'])->isSuccessful()) {
            return $this->fail('No se pudo hacer checkout --detach a main en el worktree dedicado.', true);
        }

        // #9990345 (adaptado a #9990644) — el checkout puede reportar éxito y aun así no dejar HEAD
        // detached si algo (colisión entre dos drains, ver flock) lo mueve justo después; confirma
        // antes de tocar nada más, en vez de mergear a ciegas. 'HEAD' literal = detached de verdad.
        $headTrasCheckout = trim($this->git(['rev-parse', '--abbrev-ref', 'HEAD'])->getOutput());
        if ($headTrasCheckout !== 'HEAD') {
            $this->registrarFalloAterrizaje($item, 'checkout_no_quedo_en_main', $branch, $headTrasCheckout, null);

            return $this->fail(
                "El worktree dedicado no quedó detached en main (HEAD='{$headTrasCheckout}'); otro "
                . 'proceso lo movió justo después del checkout. Merge abortado antes de tocar nada.',
                true
            );
        }

        // FASE 1: merge staged SIN commitear → detecta conflictos sin dejar rastro.
        $merge = $this->git(['merge', '--no-ff', '--no-commit', $branch]);
        if (! $merge->isSuccessful()) {
            $salida = trim($merge->getErrorOutput() . "\n" . $merge->getOutput());
            $this->git(['merge', '--abort']);

            return $this->fail("Conflicto al mergear {$branch} → abortado, main intacto.\n" . $salida, true);
        }

        // GUARD DE FRONTEND (#fin-de-semana): si el gate está ON y el merge staged toca frontend
        // (.vue/.js/.ts/.css/.scss), NO se auto-mergea — se pone EN COLA para la revisión VISUAL de
        // Irving. `regression()` solo hace php -l + boot (NO renderiza), así que un frontend que
        // COMPILA pero truena en runtime tumbaría la Torre en ausencia. Reversible: el cambio queda
        // en su rama; Irving lo mergea a mano si está bien. Toggle: setting `circuito_frontend_gate`.
        if ($this->frontendGateOn()) {
            $staged = array_values(array_filter(preg_split('/\R/', trim(
                $this->git(['diff', '--cached', '--name-only'])->getOutput()
            ))));
            $fe = array_values(array_filter($staged, fn ($f) => (bool) preg_match('/\.(vue|jsx?|tsx?|css|scss|sass)$/i', (string) $f)));
            if ($fe !== []) {
                $this->git(['merge', '--abort']);

                return $this->holdForReview($item, $branch, $fe);
            }
        }

        // FASE 2: verificación de regresión sobre el árbol ya fusionado (aún sin commit).
        $reg = $this->regression();
        if (! $reg['ok']) {
            $this->git(['merge', '--abort']);

            return $this->fail("Regresión al integrar {$branch}: {$reg['detalle']}\nMerge abortado, main intacto.", true);
        }

        // FASE 3: finaliza el merge (commit).
        $this->antesDeCommitParaPruebas(); // no-op en producción; seam de prueba (#9990345), ver abajo.
        $commit = $this->git(['commit', '--no-edit', '-m', "Integra circuito #{$item->id} ({$branch}) a main"]);
        if (! $commit->isSuccessful()) {
            $this->git(['merge', '--abort']);

            return $this->fail("No se pudo commitear el merge de {$branch}.\n" . trim($commit->getErrorOutput()), true);
        }

        $sha = trim($this->git(['rev-parse', 'HEAD'])->getOutput());

        // #9990345 (adaptado a #9990644) — ESTE es el candado que habría atajado el incidente real:
        // el commit se creó con éxito (git no reporta error alguno), pero si algo reataría HEAD a
        // una rama real durante el merge (colisión entre dos drains; en este worktree exclusivo ya
        // no hay sesiones interactivas que puedan hacerlo — ver docblock de la clase), el commit
        // quedaría colgado de ESA rama en vez de avanzar main. Antes de tocar `refs/heads/main`,
        // confirma que seguimos detached (nadie readoptó HEAD); si no, NO se toca el item ni se
        // mueve main — se deja el commit donde quedó y se escala para revisión manual.
        $headTrasCommit = trim($this->git(['rev-parse', '--abbrev-ref', 'HEAD'])->getOutput());
        if ($headTrasCommit !== 'HEAD') {
            $this->registrarFalloAterrizaje($item, 'merge_no_aterrizo_en_main', $branch, $headTrasCommit, $sha);

            return $this->fail(
                "El merge de {$branch} se commiteó ({$sha}) pero NO quedó libre para avanzar main — "
                . "HEAD terminó adoptado por '{$headTrasCommit}' (algo movió el worktree dedicado "
                . 'durante el merge). El item NO se marca integrado; el commit se deja donde quedó '
                . '(sin reescribir historia ni borrar ramas) para revisión manual.',
                true
            );
        }

        // Avanza `refs/heads/main` al nuevo commit. `update-ref` es plumbing: a diferencia de
        // `git branch -f`/`git checkout` (que git BLOQUEA si la rama está checada en otro worktree
        // — verificado empíricamente, es justo lo que impide adoptar `main` aquí mismo arriba),
        // `update-ref` sí puede mover una rama aunque esté checada en /var/www/megaisp. No toca ese
        // checkout en absoluto (solo el puntero compartido); `syncCheckoutPrincipal()` es quien,
        // best-effort y sin arriesgar nada, intenta reflejarlo ahí después.
        $avance = $this->git(['update-ref', 'refs/heads/main', $sha]);
        if (! $avance->isSuccessful()) {
            $this->registrarFalloAterrizaje($item, 'no_se_pudo_avanzar_main', $branch, $headTrasCommit, $sha);

            return $this->fail(
                "El merge de {$branch} se commiteó ({$sha}) pero no se pudo avanzar refs/heads/main: "
                . trim($avance->getErrorOutput()),
                true
            );
        }

        $this->markMerged($item, $sha, $branch);

        // #9990644 (q2, aprobado por Irving) — best-effort: refleja el avance de main en
        // /var/www/megaisp (lo que sirve www-data) SOLO si ese checkout está en `main` y limpio;
        // si tiene otra rama o cambios sin commitear, NO SE TOCA (ver docblock del método). Un
        // fallo aquí nunca revierte el merge, que ya quedó firme en `main`.
        $sincronizado = $this->syncCheckoutPrincipal($sha);

        // #711 (Jarvis Parte 1) — "al cambiar main" y "al cerrarse un item" son el MISMO evento
        // en este flujo (un item se cierra integrándose aquí), así que este es el único punto de
        // enganche que hace falta para que el índice vivo no se quede atrás. Best-effort: un
        // fallo al reindexar NUNCA debe tumbar un merge ya commiteado.
        $this->jarvisIndice->regenerarSilencioso();

        // #432 ADENDA A / #9990466 — "mergeado" = DESPLEGADO y VISIBLE, no solo en git. `markMerged()`
        // ya clasificó UI vs backend (`$item->revision_ui`); YA NO se recompila aquí síncrono
        // por-merge (eso era lo que congelaba el despacho ~4min por cada merge en cola) — solo se
        // señala al `drain()` que llamó que hace falta un rebuild, y éste lo coalesce en UNO solo
        // al final de drenar toda la cola, detached. #9990644 — un rebuild solo tiene sentido si
        // /var/www/megaisp YA tiene el código nuevo en disco (si el sync se difirió, recompilar ahí
        // construiría el bundle VIEJO); se re-intentará en el siguiente merge que sí sincronice.
        $necesitaRebuild = (bool) $item->revision_ui && $sincronizado;
        $rebuild = match (true) {
            $necesitaRebuild => ' Bundle pendiente de recompilar (coalescido con el resto del drain).',
            (bool) $item->revision_ui => ' Bundle NO recompilado: /var/www/megaisp no se pudo sincronizar todavía (ver log).',
            default => '',
        };

        Log::channel('roadmap_externo')->info('merge-ok', ['item' => $item->id, 'branch' => $branch, 'merge_commit' => $sha,
            'sincronizado' => $sincronizado, 'trigger' => $req['trigger'] ?? '?']);

        return ['estado' => 'ok', 'ok' => true, 'merge_commit' => $sha,
            'salida' => "Integrada a dev (merge {$sha}). Regresión OK.{$rebuild}", 'escalado' => false,
            'necesita_rebuild' => $necesitaRebuild, 'at' => time()];
    }

    /**
     * No-op en producción. Seam de prueba (#9990345): un test lo sobreescribe para simular que
     * otro proceso movió HEAD a otra rama justo antes de que el runner commitee el merge — el
     * punto exacto donde ocurrió el incidente real (el commit se crea, pero con HEAD en otro lado).
     */
    protected function antesDeCommitParaPruebas(): void
    {
    }

    /**
     * Deja rastro en el log del item cuando una verificación de aterrizaje en main falla, con la
     * rama real donde quedó HEAD — para que el diagnóstico no dependa de reconstruir el grafo a
     * mano (#9990345). `$sha` es null cuando la verificación es previa al commit.
     */
    private function registrarFalloAterrizaje(RoadmapItem $item, string $evento, string $branch, string $headReal, ?string $sha): void
    {
        $log = $item->log ?: [];
        $log[] = ['ts' => now()->toIso8601String(), 'por' => 'merge-runner', 'evento' => $evento,
            'branch' => $branch, 'head_branch_real' => $headReal, 'commit_huerfano' => $sha];
        $item->log = $log;
        $item->save();

        Log::channel('roadmap_externo')->error($evento, ['item' => $item->id, 'branch' => $branch,
            'head_real' => $headReal, 'commit' => $sha]);
    }

    /**
     * Ruta del worktree donde corre el merge. #9990644: en producción SIEMPRE `MERGE_WORKTREE`
     * (el worktree dedicado y exclusivo), NUNCA `base_path()` (que en este box resuelve al checkout
     * COMPARTIDO /var/www/megaisp). Un test la sobreescribe para apuntar a un repo temporal
     * aislado, sin tocar ningún checkout real (#9990345).
     */
    protected function workDir(): string
    {
        return self::MERGE_WORKTREE;
    }

    /**
     * Ruta del checkout principal (/var/www/megaisp) que sirve www-data. Separada de `workDir()`
     * (#9990644): ahí NUNCA corre el merge, solo recibe la sincronización best-effort de
     * `syncCheckoutPrincipal()`. En producción SIEMPRE `base_path()` (justo lo que hoy resuelve a
     * /var/www/megaisp). Un test la sobreescribe IGUAL a `workDir()` para que la sincronización se
     * autodesactive (mismo path = no-op, ver `syncCheckoutPrincipal()`).
     */
    protected function checkoutPrincipalPath(): string
    {
        return base_path();
    }

    /** Verificación de regresión ligera sobre el árbol fusionado (sin correr la suite destructiva). */
    protected function regression(): array
    {
        // (a) php -l de los .php cambiados por el merge (staged).
        $changed = array_filter(preg_split('/\R/', trim(
            $this->git(['diff', '--cached', '--name-only'])->getOutput()
        )));
        foreach ($changed as $f) {
            if (! str_ends_with($f, '.php')) {
                continue;
            }
            $path = $this->workDir() . '/' . $f;
            if (! is_file($path)) {
                continue; // borrado por el merge
            }
            $lint = new Process(['php', '-l', $path], $this->workDir());
            $lint->run();
            if (! $lint->isSuccessful()) {
                return ['ok' => false, 'detalle' => "php -l falló en {$f}: " . trim($lint->getErrorOutput() . $lint->getOutput())];
            }
        }

        // (b) el framework bootea con el código fusionado (caza fatales de carga). Corre en
        // workDir() (#9990644): ahí es donde vive el árbol recién fusionado, no en base_path().
        $boot = new Process(['php', 'artisan', '--version'], $this->workDir());
        $boot->setTimeout(60);
        $boot->run();
        if (! $boot->isSuccessful()) {
            return ['ok' => false, 'detalle' => 'php artisan no bootea con el código fusionado: ' . trim($boot->getErrorOutput())];
        }

        return ['ok' => true, 'detalle' => 'OK'];
    }

    /**
     * #9990644 (Fase A, pregunta q2 — Opción 1 aprobada por Irving) — refleja el avance de `main`
     * (ya movido por `performMerge()` vía `update-ref`, ver ahí) en el checkout principal
     * (`checkoutPrincipalPath()`, /var/www/megaisp en producción). BEST-EFFORT y ESTRICTAMENTE
     * NO DESTRUCTIVO:
     *  - Si `checkoutPrincipalPath()` === `workDir()` (el caso de los tests: ambos seams
     *    sobreescritos al mismo repo temporal) → no-op explícito, `true` (nada que sincronizar).
     *  - Si ese checkout NO está en la rama `main` (alguien tiene una rama de trabajo checada a
     *    mano ahí) → NO SE TOCA absolutamente nada — ni checkout, ni reset, ni fetch. `main` ya
     *    avanzó en el repositorio compartido; ese checkout se pondrá al día solo la próxima vez
     *    que sí esté en main. Devuelve `false` (no sincronizado esta vez).
     *  - Si SÍ está en `main` pero tiene cambios sin commitear (`git status --porcelain` no vacío)
     *    → tampoco se toca (un `reset --hard` los destruiría) — se loguea fuerte para que alguien
     *    lo resuelva a mano. Devuelve `false`.
     *  - Si está en `main` y limpio → `git reset --hard HEAD` (HEAD ahí es un ref simbólico a
     *    `refs/heads/main`, que ya apunta al commit nuevo tras el `update-ref` de arriba; esto solo
     *    pone el índice/árbol de trabajo al día con su propio HEAD, no descarta nada real porque ya
     *    verificamos que está limpio). Devuelve `true` si terminó sincronizado de verdad.
     *
     * Un fallo aquí NUNCA revierte el merge (que ya quedó firme en `main`) ni escala el item.
     */
    protected function syncCheckoutPrincipal(string $sha): bool
    {
        $principal = $this->checkoutPrincipalPath();
        if ($principal === $this->workDir()) {
            return true; // mismo path (tests): nada que sincronizar.
        }

        try {
            $rama = trim((new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $principal))->mustRun()->getOutput());
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->warning('sync-checkout-principal-no-branch', ['error' => $e->getMessage()]);

            return false;
        }

        if ($rama !== 'main') {
            Log::channel('roadmap_externo')->info('sync-checkout-principal-omitido', [
                'motivo' => 'checkout principal en otra rama', 'rama_actual' => $rama, 'merge_commit' => $sha,
            ]);

            return false;
        }

        $status = new Process(['git', 'status', '--porcelain'], $principal);
        $status->run();
        if (! $status->isSuccessful() || trim($status->getOutput()) !== '') {
            Log::channel('roadmap_externo')->warning('sync-checkout-principal-sucio', [
                'motivo' => 'checkout principal tiene cambios sin commitear, no se sincroniza para no perderlos',
                'merge_commit' => $sha,
            ]);

            return false;
        }

        $reset = new Process(['git', 'reset', '--hard', 'HEAD'], $principal);
        $reset->setTimeout(60);
        $reset->run();
        if (! $reset->isSuccessful()) {
            Log::channel('roadmap_externo')->warning('sync-checkout-principal-fallo', [
                'merge_commit' => $sha, 'error' => trim($reset->getErrorOutput()),
            ]);

            return false;
        }

        Log::channel('roadmap_externo')->info('sync-checkout-principal-ok', ['merge_commit' => $sha]);

        return true;
    }

    /**
     * #9990466 — dispara el rebuild del bundle DETACHED (setsid nohup … &, mismo patrón que
     * `SchedulerCommand::lanzarVueltaItem()`) para que NO bloquee a quien llamó a `drain()` — el
     * scheduler sostiene `scheduler.lock` durante todo `drain()`, así que un rebuild síncrono aquí
     * volvería a congelar el despacho, que es justo lo que este item corrige. Serializado con
     * `BUILD_LOCK` (flock del binario `flock(1)`, bloqueante): si ya hay un rebuild post-merge en
     * curso, este espera su turno en vez de correr encimado — así el bundle final queda consistente
     * con TODO lo que aterrizó, sin duplicar compilaciones. Corre bajo el SEMÁFORO general de builds
     * (deploy/circuito/npm-build.sh) para no chocar con builds del ejecutor; CIRCUITO_BUILD_MODE=prod
     * → build de producción. NUNCA config:cache (rompe env() en runtime en este box → IA/WhatsApp
     * NULL). Best-effort: un fallo de build NO revierte el merge (el código ya está en main) ni
     * tumba el scheduler; queda en su propio log.
     */
    protected function triggerRebuildAsync(): void
    {
        try {
            $script = base_path('deploy/circuito/npm-build.sh');
            $log    = storage_path('logs/rebuild-post-merge.log');
            $interno = 'CIRCUITO_BUILD_MODE=prod bash ' . escapeshellarg($script)
                . '; RC=$?'
                . '; php artisan view:clear; php artisan route:clear; php artisan config:clear; php artisan view:cache'
                . '; exit $RC';
            $cmd = 'exec 3>&- 4>&- 5>&- 6>&- 7>&- 8>&- 9>&- 2>/dev/null; setsid nohup flock '
                . escapeshellarg(self::BUILD_LOCK) . ' -c ' . escapeshellarg($interno)
                . ' >> ' . escapeshellarg($log) . ' 2>&1 &';

            Process::fromShellCommandline($cmd, base_path())->run();

            Log::channel('roadmap_externo')->info('rebuild-post-merge-disparado', ['at' => time()]);
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->warning('rebuild-post-merge-disparo-fallo', ['error' => $e->getMessage()]);
        }
    }

    /** Toggle del guard de frontend: setting `circuito_frontend_gate`='1' (OFF por default). */
    private function frontendGateOn(): bool
    {
        return (string) \Illuminate\Support\Facades\DB::table('settings')
            ->where('key', 'circuito_frontend_gate')->value('value') === '1';
    }

    /**
     * Frontend tocado con el gate ON: aborta el merge y deja el item EN COLA para la revisión visual
     * de Irving (estado requiere_irving). NO es un error — el cambio queda intacto en su rama.
     * Devuelve escalado=false porque ya fijamos el estado aquí (evita el re-log "escalado" del drain).
     */
    private function holdForReview(RoadmapItem $item, string $branch, array $fe): array
    {
        $lista = implode(', ', array_slice($fe, 0, 6)) . (count($fe) > 6 ? '…' : '');
        $item->estado_aprobacion  = 'requiere_irving';
        $item->revision_ui        = true;
        $item->revisado_at        = now();
        $item->aprobado_por       = 'merge-runner(frontend-gate)';
        $item->comentarios_claude = (string) $item->comentarios_claude
            . "\n\n--- EN COLA PARA REVISIÓN VISUAL (guard frontend, " . now()->toDateTimeString() . ") ---\n"
            . "El merge de {$branch} toca frontend ({$lista}); con el gate ON NO se auto-mergea para que un "
            . "render roto no tumbe la Torre en tu ausencia. Revísalo visual y mergéalo a mano si está bien "
            . "(git merge --no-ff {$branch}), o quita el gate (setting circuito_frontend_gate=0).\n";
        $item->save();

        \Illuminate\Support\Facades\Log::channel('roadmap_externo')
            ->info('merge-frontend-en-cola', ['item' => $item->id, 'branch' => $branch, 'archivos' => $fe]);

        return ['estado' => 'en_cola', 'ok' => false, 'escalado' => false, 'merge_commit' => null,
            'salida' => "Frontend EN COLA para tu revisión visual (no auto-mergeado): {$lista}", 'at' => time()];
    }

    /** Marca el item como integrado + CLASIFICA UI/backend y auto-archiva lo backend. */
    private function markMerged(RoadmapItem $item, string $sha, string $branch): void
    {
        // #9990732 — GUARDA LO CRÍTICO PRIMERO. `main` ya avanzó (update-ref, en performMerge())
        // cuando se llega aquí; si el proceso muere (timeout/kill/OOM) en cualquier paso de ABAJO
        // (clasificarUi() dispara un `git diff` en subproceso — el punto real de riesgo), el item
        // debe quedar YA marcado `completado` con su `merge_commit`, no huérfano indistinguible de
        // "nadie lo trabajó" (eso volvía al reaper re-encolarlo de por vida pese a estar en main).
        $item->merge_commit = $sha;
        if ($item->status !== 'done') {
            $item->status = 'done';
        }
        $item->estado_aprobacion = 'completado';
        $item->save();

        // Resto: enriquecimiento NO esencial — si algo de esto falla, ya no deja al item huérfano.
        try {
            // #1035 — el merge a main ES el momento en que «decisión tomada» pasa a «decisión
            // ejecutada»: estampa el hash en cada pregunta del brief que ya tenía opción elegida.
            $item->marcarPreguntasEjecutadas($sha, 'merge-runner');

            // Clasificación UI vs backend por los archivos que trajo el merge (HEAD^1..HEAD = main previo..fusión).
            $clasif = $this->clasificarUi($sha);
            $item->revision_ui = $clasif['ui'];
            $item->ui_hint     = $clasif['hint'];

            // Backend/interno (sin efecto visible) → fuera del radar: AUTO-ARCHIVA (queda en Historial, reversible).
            // UI-verificable → NO se archiva: espera la revisión visual de Irving.
            if ($clasif['ui'] === false) {
                $item->archivado_at  = now();
                $item->archivado_por = 'merge-runner (backend auto)';
            }

            $log = $item->log ?: [];
            $log[] = ['ts' => now()->toIso8601String(), 'por' => 'merge-runner', 'evento' => 'merge_a_main',
                'branch' => $branch, 'merge_commit' => $sha,
                'revision_ui' => $clasif['ui'], 'archivado' => ($clasif['ui'] === false)];
            $item->log = $log;
            $item->save();
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->warning('markmerged-enriquecimiento-fallo', [
                'item' => $item->id, 'merge_commit' => $sha, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ¿El merge tocó archivos con EFECTO VISIBLE en la UI? (.vue/.blade.php/.css/.scss/resources/js).
     * Devuelve ['ui'=>bool, 'hint'=>?string]. `hint` (solo para UI) resume QUÉ cambió / DÓNDE mirarlo.
     * Si no se pueden listar los archivos → ui=true (falla-seguro: mejor pedir revisión de más que archivar de más).
     */
    private function clasificarUi(string $sha): array
    {
        // Archivos que la fusión incorporó a main (primer padre = main previo; el merge = $sha).
        $out = $this->git(['diff', '--name-only', $sha . '^1', $sha])->getOutput();
        $files = array_values(array_filter(preg_split('/\R/', trim($out))));

        if (! $files) {
            return ['ui' => true, 'hint' => 'No se pudieron listar los archivos del merge — marcado para revisión visual por precaución.'];
        }

        $esUi = static function (string $f): bool {
            return str_ends_with($f, '.vue')
                || str_ends_with($f, '.blade.php')
                || str_ends_with($f, '.css')
                || str_ends_with($f, '.scss')
                || str_starts_with($f, 'resources/js/');
        };

        $uiFiles = array_values(array_filter($files, $esUi));
        if (! $uiFiles) {
            return ['ui' => false, 'hint' => null];
        }

        // Pista para el radar visual: dónde mirar (rutas UI, hasta 6) + cuántos archivos totales.
        $muestra = array_slice($uiFiles, 0, 6);
        $extra   = count($uiFiles) - count($muestra);
        $donde   = implode(', ', $muestra) . ($extra > 0 ? " (+{$extra} más)" : '');
        $hint    = 'Cambió la interfaz. Revisar en el navegador: ' . $donde
            . '. Probar que la pantalla afectada carga y el cambio se ve/funciona; recompilar assets si aplica (npm run dev).';

        return ['ui' => true, 'hint' => $hint];
    }

    /** Construye un resultado de FALLO; $escalate marca que el item debe ir a la bandeja de Irving. */
    private function fail(string $salida, bool $escalate): array
    {
        return ['estado' => 'error', 'ok' => false, 'merge_commit' => null,
            'salida' => $salida, 'escalado' => $escalate, 'at' => time()];
    }

    /**
     * ¿Hay cambios sin commitear en archivos que este merge TAMBIÉN toca?
     *
     * Devuelve la lista (recortada, para el mensaje de error) de los archivos en conflicto, o null
     * si no hay ninguno — que es el caso normal aunque el árbol tenga otras cosas sucias.
     *
     * FAIL-CLOSED: si no se puede calcular el footprint de la rama (ref rara, git que falla), se
     * cae al criterio conservador de antes y se reporta TODO lo sucio → el merge se rechaza. Nunca
     * al revés: la duda jamás abre la puerta a pisar trabajo no commiteado.
     */
    private function sucioEnConflictoCon(string $branch): ?string
    {
        // Cambios sin commitear en archivos TRACKEADOS (staged + unstaged) vs HEAD.
        // -z evita el quoting de git para rutas con espacios/acentos (este repo tiene ambos).
        $diffSucio = $this->git(['diff', '--name-only', '-z', 'HEAD']);
        if (! $diffSucio->isSuccessful()) {
            return 'no se pudo leer el estado del árbol';
        }
        $sucios = $this->rutasZ($diffSucio->getOutput());
        if (! $sucios) {
            return null; // árbol limpio: nada que proteger
        }

        // Footprint del merge = lo que la rama cambió desde su punto de partida con main.
        $diffRama = $this->git(['diff', '--name-only', '-z', 'main...' . $branch]);
        if (! $diffRama->isSuccessful()) {
            return $this->listaCorta($sucios); // fail-closed
        }
        $footprint = $this->rutasZ($diffRama->getOutput());

        $choque = array_values(array_intersect($sucios, $footprint));

        return $choque ? $this->listaCorta($choque) : null;
    }

    /** Parte una salida de git `-z` (rutas separadas por NUL) en un arreglo de rutas. */
    private function rutasZ(string $out): array
    {
        return array_values(array_filter(explode("\0", $out), fn ($r) => $r !== ''));
    }

    /** Lista legible para el mensaje de error: primeras 5 rutas + "y N más". */
    private function listaCorta(array $rutas): string
    {
        $muestra = array_slice($rutas, 0, 5);
        $resto   = count($rutas) - count($muestra);

        return implode(', ', $muestra) . ($resto > 0 ? " y {$resto} más" : '');
    }

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), $this->workDir());
        $p->setTimeout(120);
        $p->run();

        return $p;
    }
}
