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
 * on-box como meganet en el checkout PRINCIPAL (/var/www/megaisp, donde vive `main`), drena la cola.
 *
 * Garantías (lo que pidió Irving):
 *  - Corre en el checkout principal (donde `main` está checado) — resuelto por base_path().
 *  - SERIALIZADO: flock('merge.lock') → un merge a la vez, aunque lo llamen varios pickers.
 *  - Verificación de REGRESIÓN antes de aplicar: merge en 2 fases (--no-commit → verifica → commit).
 *  - Fallo (conflicto/regresión/permiso) → ABORTA, deja main intacto, escala el item a requiere_irving
 *    y GUARDA el error (mergeResult) para que la Torre lo MUESTRE. Nunca silencioso.
 *  - Kill switch: los merges se drenan siempre (una decisión ya tomada); el pause solo frena al ejecutor.
 */
class MergeRunner
{
    private const LOCK = '/home/meganet/circuito/merge.lock';

    public function __construct(private RoadmapCircuitoService $svc)
    {
    }

    /**
     * Drena la cola de merge (serializado por flock). Devuelve la lista de resultados.
     * Si el lock está ocupado (otro drain corriendo) devuelve [] sin bloquear.
     */
    public function drain(): array
    {
        @mkdir(dirname(self::LOCK), 0775, true);
        $lock = @fopen(self::LOCK, 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return []; // ya hay un drain en curso
        }

        $out = [];
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

        // El árbol principal debe estar limpio (solo tracked); untracked no afecta al merge.
        if (trim($this->git(['status', '--porcelain', '--untracked-files=no'])->getOutput()) !== '') {
            return $this->fail('El checkout principal tiene cambios sin commitear; no se puede mergear con seguridad.', true);
        }

        // Asegura estar en main (donde vive el working tree de dev).
        if (! $this->git(['checkout', 'main'])->isSuccessful()) {
            return $this->fail('No se pudo cambiar a main en el checkout principal.', true);
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
        $commit = $this->git(['commit', '--no-edit', '-m', "Integra circuito #{$item->id} ({$branch}) a main"]);
        if (! $commit->isSuccessful()) {
            $this->git(['merge', '--abort']);

            return $this->fail("No se pudo commitear el merge de {$branch}.\n" . trim($commit->getErrorOutput()), true);
        }

        $sha = trim($this->git(['rev-parse', 'HEAD'])->getOutput());
        $this->markMerged($item, $sha, $branch);

        Log::channel('roadmap_externo')->info('merge-ok', ['item' => $item->id, 'branch' => $branch, 'merge_commit' => $sha,
            'trigger' => $req['trigger'] ?? '?']);

        return ['estado' => 'ok', 'ok' => true, 'merge_commit' => $sha,
            'salida' => "Integrada a dev (merge {$sha}). Regresión OK.", 'escalado' => false, 'at' => time()];
    }

    /** Verificación de regresión ligera sobre el árbol fusionado (sin correr la suite destructiva). */
    private function regression(): array
    {
        // (a) php -l de los .php cambiados por el merge (staged).
        $changed = array_filter(preg_split('/\R/', trim(
            $this->git(['diff', '--cached', '--name-only'])->getOutput()
        )));
        foreach ($changed as $f) {
            if (! str_ends_with($f, '.php')) {
                continue;
            }
            $path = base_path($f);
            if (! is_file($path)) {
                continue; // borrado por el merge
            }
            $lint = new Process(['php', '-l', $path], base_path());
            $lint->run();
            if (! $lint->isSuccessful()) {
                return ['ok' => false, 'detalle' => "php -l falló en {$f}: " . trim($lint->getErrorOutput() . $lint->getOutput())];
            }
        }

        // (b) el framework bootea con el código fusionado (caza fatales de carga).
        $boot = new Process(['php', 'artisan', '--version'], base_path());
        $boot->setTimeout(60);
        $boot->run();
        if (! $boot->isSuccessful()) {
            return ['ok' => false, 'detalle' => 'php artisan no bootea con el código fusionado: ' . trim($boot->getErrorOutput())];
        }

        return ['ok' => true, 'detalle' => 'OK'];
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
        $item->merge_commit = $sha;
        if ($item->status !== 'done') {
            $item->status = 'done';
        }
        $item->estado_aprobacion = 'completado';

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

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), base_path());
        $p->setTimeout(120);
        $p->run();

        return $p;
    }
}
