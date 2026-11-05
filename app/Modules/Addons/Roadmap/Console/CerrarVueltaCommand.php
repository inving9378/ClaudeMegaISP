<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * #1078 — CIERRE DE CADA VUELTA, en el momento en que termina.
 *
 * Antes, si `claude -p` terminaba bien (RC=0) sin cerrar su item, nadie decidía nada: el último
 * item lo soltaba el `trap EXIT` de `vuelta.sh` (`circuito:soltar-claim`, pensado para muertes del
 * proceso) y los de en medio del pool los recogía el reaper. Los dos lo devolvían a la cola y otra
 * terminal lo tomaba al minuto siguiente: del 23 al 27-oct, 268 de las 361 vueltas sin cambio
 * fueron el mismo item relanzado (#47 ×82, #121 ×82, #32 ×71, #28 ×69).
 *
 * Solo actúa si el item SIGUE `en_progreso` con el `worker_sid` de esta vuelta y si `claude -p`
 * terminó bien; un fin anormal lo decide `circuito:parquear-timeout`, como siempre:
 *
 *   1. Le quedan sub-items abiertos → se aparca como paraguas (lo cierra el último hijo).
 *   2. Hubo commits en su rama durante la vuelta → avanzó: vuelve a la cola.
 *   3. Nada cambió → pasa a «Esperan tu decisión» (requiere_irving) con lo que dijo la terminal,
 *      y suma una vuelta vacía: si algo automático lo re-aprueba, el pool espera 1 h / 4 h / 24 h.
 *
 * Aparte, si la terminal SÍ intentó cerrarlo como `completado` con código en su rama pero sin pasar
 * por `circuito:integrar`, el candado de cierre (`verificarCierre`) lo aparca fuera del pool sin
 * worker. Antes quedaba ahí, apartado y sin que nadie lo viera como «espera merge» (#708 y #1119,
 * 30-oct, frenando a 7 secciones del CRM). Ahora se manda al camino normal de integración, con sus
 * mismas reglas: A/B a la fila del runner, C (o auto-merge apagado) a «Esperan tu merge».
 *
 * Nunca cambia el código de salida de la vuelta.
 */
class CerrarVueltaCommand extends Command
{
    protected $signature = 'circuito:cerrar-vuelta
        {item : id del item de la vuelta que acaba de terminar}
        {--sid= : worker que lo reclamó; solo se toca si el claim sigue siendo suyo}
        {--rc= : código de salida de claude -p}
        {--inicio= : epoch del arranque de la vuelta (para contar solo sus commits)}
        {--log= : log del worker, para citar la explicación de la terminal}';

    protected $description = 'Decide qué pasa con un item cuya vuelta terminó sin cerrarlo (#1078).';

    /** Vueltas vacías seguidas (sin aprobación humana en medio) antes de mandar el item a la bandeja. */
    public const FALLOS_ANTES_DE_BANDEJA = 2;

    public function handle(): int
    {
        $id   = (int) $this->argument('item');
        $sid  = trim((string) $this->option('sid'));
        $item = RoadmapItem::find($id);

        if (! $item || $sid === '' || (string) $this->option('rc') !== '0') {
            return self::SUCCESS;   // fin anormal: lo decide parquear-timeout / soltar-claim
        }
        if ($this->cierreSinMergeDeEstaVuelta($item, (int) $this->option('inicio'))) {
            $log   = $item->log ?: [];
            $log[] = [
                'ts' => now()->toIso8601String(), 'por' => 'cerrar-vuelta', 'evento' => 'cierre_sin_merge_a_integracion',
                'sid' => $sid, 'rama' => $item->branch,
                'motivo' => 'La terminal lo cerró con código en su rama pero sin integrarlo; el candado de cierre lo '
                    . 'apartó. Se manda al camino normal de integración (A/B a la fila del merge, C a «Esperan tu merge»).',
            ];
            $item->log = $log;
            $item->save();
            $this->call('circuito:integrar', ['id' => $item->id]);

            return self::SUCCESS;
        }
        if ($item->estado_aprobacion !== 'en_progreso' || (string) $item->worker_sid !== $sid) {
            return self::SUCCESS;   // la vuelta lo cerró, o ya es de otra terminal
        }

        if ($item->tieneSubItemsAbiertos()) {
            $item->aparcarComoParaguas();
            $item->save();
            $this->info("#{$id}: se descompuso; queda como paraguas.");

            return self::SUCCESS;
        }

        $inicio  = (int) $this->option('inicio');
        $commits = $this->commitsDeLaVuelta($item, $inicio);
        $log     = $item->log ?: [];

        if ($commits > 0) {
            $destino = in_array($item->estado_previo_claim, ['aprobado_irving', 'aprobado_revisor', 'aprobado_claude'], true)
                ? $item->estado_previo_claim
                : 'aprobado_revisor';
            $log[] = [
                'ts' => now()->toIso8601String(), 'por' => 'cerrar-vuelta', 'evento' => 'vuelta_con_avance_sin_cierre',
                'sid' => $sid, 'commits' => $commits, 'estado' => $destino,
                'motivo' => "La vuelta de {$sid} dejó {$commits} commit(s) sin cerrar el item: avanzó, vuelve a la cola como {$destino}.",
            ];
            $item->estado_aprobacion = $destino;
            $this->info("#{$id}: avanzó ({$commits} commits); vuelve a la cola como {$destino}.");
        } else {
            $item->registrarVueltaVacia();
            $explicacion = $this->explicacionDeLaTerminal((string) $this->option('log'));
            // Limpieza de la Hoja de ruta (regla de Irving, 2026-10-30): la PRIMERA vuelta vacía vuelve a
            // la cola con la espera creciente (1 h; el pool no la salta). Al SEGUNDO fallo sin una
            // aprobación humana de por medio (que reinicia `vueltas_vacias`) va a «Esperan tu decisión»
            // con la causa, y ya no se reintenta solo.
            if ((int) $item->vueltas_vacias < self::FALLOS_ANTES_DE_BANDEJA) {
                $destino = in_array($item->estado_previo_claim, ['aprobado_irving', 'aprobado_revisor', 'aprobado_claude'], true)
                    ? $item->estado_previo_claim
                    : 'aprobado_revisor';
                $hasta = optional($item->esperaVueltasVaciasHasta())->format('H:i');
                $log[] = [
                    'ts' => now()->toIso8601String(), 'por' => 'cerrar-vuelta', 'evento' => 'vuelta_vacia_reintento',
                    'sid' => $sid, 'vueltas_vacias' => (int) $item->vueltas_vacias, 'estado' => $destino,
                    'motivo' => "La vuelta de {$sid} terminó sin commits y sin cerrar el item (fallo 1 de "
                        . self::FALLOS_ANTES_DE_BANDEJA . "): vuelve a la cola como {$destino} tras la espera creciente"
                        . ($hasta ? " (desde las {$hasta})" : '') . '. Si vuelve a quedar vacía, pasa a «Esperan tu decisión».',
                    'explicacion_terminal' => $explicacion,
                ];
                $item->estado_aprobacion = $destino;
                $this->info("#{$id}: sin cambios (fallo 1); vuelve a la cola tras la espera creciente.");
            } else {
                $log[] = [
                    'ts' => now()->toIso8601String(), 'por' => 'cerrar-vuelta', 'evento' => 'vuelta_sin_cambio',
                    'sid' => $sid, 'vueltas_vacias' => (int) $item->vueltas_vacias, 'estado' => 'requiere_irving',
                    'motivo' => "La vuelta de {$sid} terminó sin commits y sin cerrar el item por {$item->vueltas_vacias}.ª vez: "
                        . 'pasa a «Esperan tu decisión» y no se reintenta solo. Lo que dijo la terminal va en «explicacion_terminal».',
                    'explicacion_terminal' => $explicacion,
                ];
                $item->estado_aprobacion = 'requiere_irving';
                $item->motivo_espera     = $item->motivo_espera ?: 'decision';
                $this->info("#{$id}: sin cambios por {$item->vueltas_vacias}.ª vez; pasa a «Esperan tu decisión».");
            }
        }

        $item->log        = $log;
        $item->worker_sid = null;
        $item->claimed_at = null;
        $item->save();

        return self::SUCCESS;
    }

    /**
     * ¿El candado de cierre aparcó el item EN ESTA VUELTA porque traía rama con código y sin merge?
     * Es justo el estado que deja `RoadmapItem::saving` (aprobado_irving, fuera del pool, sin worker).
     */
    private function cierreSinMergeDeEstaVuelta(RoadmapItem $item, int $inicio): bool
    {
        if ($item->estado_aprobacion !== 'aprobado_irving' || ! $item->excluir_pool_automatico
            || $item->merge_commit || $item->esperando_merge_irving || $item->worker_sid || ! $item->branch) {
            return false;
        }

        $ultimo = collect($item->log ?: [])->last(fn ($e) => ($e['evento'] ?? null) === 'cierre_incompleto');
        if (! $ultimo || empty($ultimo['bloqueantes']) || ($inicio > 0 && strtotime((string) ($ultimo['ts'] ?? '')) < $inicio)) {
            return false;
        }

        $p = new Process(['git', 'rev-list', '--count', 'main..' . $item->branch], base_path());
        $p->run();

        return $p->isSuccessful() && (int) trim($p->getOutput()) > 0;
    }

    /** Commits de la rama del item hechos desde que arrancó la vuelta (todos, si no se sabe cuándo). */
    private function commitsDeLaVuelta(RoadmapItem $item, int $inicio): int
    {
        $ramas = $item->branch ? [(string) $item->branch] : [];
        $p = new Process(['git', 'for-each-ref', '--format=%(refname:short)', "refs/heads/circuito/item-{$item->id}-*"], base_path());
        $p->run();
        foreach (preg_split('/\s+/', trim($p->getOutput())) as $r) {
            if ($r !== '') {
                $ramas[] = $r;
            }
        }

        $total = 0;
        foreach (array_unique($ramas) as $rama) {
            $cmd = ['git', 'rev-list', '--count'];
            if ($inicio > 0) {
                $cmd[] = '--since=@' . $inicio;
            }
            $cmd[] = 'main..' . $rama;
            $q = new Process($cmd, base_path());
            $q->run();
            if ($q->isSuccessful()) {
                $total += (int) trim($q->getOutput());
            }
        }

        return $total;
    }

    /** Lo último que escribió la terminal en esta vuelta: es lo que Irving necesita para decidir. */
    private function explicacionDeLaTerminal(string $log): ?string
    {
        if ($log === '' || ! is_file($log)) {
            return null;
        }
        $texto = (string) @file_get_contents($log, false, null, max(0, (int) @filesize($log) - 60000));
        $ini = strrpos($texto, '===== inicio de la vuelta');
        $fin = strrpos($texto, '===== fin de la vuelta');
        if ($ini === false || $fin === false || $fin < $ini) {
            return null;
        }
        $bloque = substr($texto, $ini, $fin - $ini);
        $bloque = trim((string) preg_replace('/^.*===== inicio de la vuelta[^\n]*\n/', '', $bloque));

        return $bloque === '' ? null : mb_substr($bloque, -700);
    }
}
