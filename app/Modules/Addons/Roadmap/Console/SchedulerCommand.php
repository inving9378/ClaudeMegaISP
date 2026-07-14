<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\MergeRunner;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * SCHEDULER del Circuito en paralelo (#334 Fase 1). Corre on-box como meganet (cron cada minuto).
 * Lanza hasta N vueltas por-item concurrentes, cada una en su worktree (wt-1..wt-N), respetando:
 *  - Kill switch (#342): si pausado → no lanza nada.
 *  - Semáforo máx N (config circuito_paralelismo): un slot = un worktree con su flock wt-K.lock.
 *  - Pre-filtro conservador nivel-módulo (#334): no paraleliza dos items del mismo `modulo`
 *    (git serializa el merge como backstop para lo dudoso/null).
 *  - Anti-colisión (#341): solo toma items `tomablePorCircuito` (excluye en_progreso/bloqueados);
 *    RECLAMA atómico (aprobado_* → en_progreso) antes de lanzar (dos rondas no toman el mismo item).
 * Un solo scheduler a la vez (flock propio). Nunca push ni prod.
 */
class SchedulerCommand extends Command
{
    protected $signature = 'circuito:scheduler {--dry : solo reporta el plan, no lanza}';

    protected $description = 'Planifica y lanza N vueltas por-item en paralelo (#334 Fase 1).';

    private const RUNTIME = '/home/meganet/circuito';
    private const SCHED_LOCK = self::RUNTIME . '/scheduler.lock';

    public function handle(RoadmapCircuitoService $svc): int
    {
        // Latido del SCHEDULER PRIMERO (antes del flock, SIEMPRE): así cada corrida del cron marca
        // "vivo" aunque otra instancia tenga el lock o esté pausado → cron_vivo confiable, sin falso
        // "cron detenido". Distingue "scheduler VIVO pero ocioso" de "cron MUERTO".
        if (! $this->option('dry')) {
            DB::table('settings')->updateOrInsert(['key' => 'circuito_scheduler_beat'], ['value' => (string) time(), 'updated_at' => now()]);
        }

        // Un solo scheduler a la vez.
        $lock = @fopen(self::SCHED_LOCK, 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return self::SUCCESS;
        }

        try {
            // Kill switch: en pausa NO lanza nada (el dry-run sí muestra el plan, no ejecuta).
            if ($svc->isPaused() && ! $this->option('dry')) {
                return self::SUCCESS;
            }

            // #432 B1 — el scheduler es el ÚNICO despachador. Aquí (no en el viejo disparo-check
            // 1-a-la-vez, eliminado) drena la cola de merges y consume el disparo manual: el botón
            // "Disparar" solo adelanta esta corrida; el reparto real es SIEMPRE en paralelo abajo.
            if (! $this->option('dry')) {
                try {
                    app(MergeRunner::class)->drain();
                } catch (\Throwable $e) {
                    // un fallo de merge no tumba el scheduler; queda registrado en su propio log.
                }
                if ($svc->pendingDisparo()) {
                    $svc->clearDisparo();
                }

                // #438 — colisión EN VUELO: detecta footprint que se pisa entre dos items ya
                // despachados (el pre-filtro de módulo de abajo no lo puede ver) y reanuda a los
                // que ya quedaron libres (su "ganador" integró/salió de vuelo). Solo lee refs +
                // escribe el flag propio del perdedor; nunca toca un worktree ajeno ni mata nada
                // en vuelo. Un fallo aquí no tumba el scheduler.
                try {
                    $svc->detectarColisionesEnVuelo();
                    $svc->reanudarColisionesResueltas();
                } catch (\Throwable $e) {
                    // best-effort, igual que el drain de arriba.
                }
            }

            $n = $svc->getParalelismo();

            // Slots libres = worktrees wt-1..wt-N cuyo flock NO está tomado por una vuelta viva.
            $freeSlots = [];
            for ($k = 1; $k <= $n; $k++) {
                if ($this->slotFree("wt-{$k}")) {
                    $freeSlots[] = $k;
                }
            }
            if (! $freeSlots) {
                return self::SUCCESS;
            }

            // Items ejecutables módulo-disjuntos (excluye módulos en vuelo), hasta #slots libres.
            $items = $svc->ejecutablesParalelo($svc->modulosEnVuelo(), count($freeSlots));
            if (! $items) {
                return self::SUCCESS;
            }

            $lanzados = [];
            foreach ($items as $i => $item) {
                if (! isset($freeSlots[$i])) {
                    break;
                }
                $slot = $freeSlots[$i];
                $id   = (int) $item['id'];

                if ($this->option('dry')) {
                    $this->line("PLAN: #{$id} (modulo=" . ($item['modulo'] ?: '—') . ") → slot wt-{$slot}");
                    $lanzados[] = $id;
                    continue;
                }

                // RECLAMO atómico: solo si sigue aprobado_* o A-pendiente (evita doble-toma).
                // Sella la firma del worker (#334 A): quién lo tomó = el slot wt-{slot}.
                $claimed = DB::table('roadmap_items')
                    ->where('id', $id)
                    ->where(function ($q) {
                        $q->whereIn('estado_aprobacion', ['aprobado_claude', 'aprobado_revisor', 'aprobado_irving'])
                            ->orWhere(fn ($x) => $x->where('nivel_riesgo', 'A')->where('estado_aprobacion', 'pendiente_revision'));
                    })
                    ->update(['estado_aprobacion' => 'en_progreso', 'worker_sid' => "wt-{$slot}", 'updated_at' => now()]);
                if ($claimed !== 1) {
                    continue; // ya lo tomó otro
                }

                $this->lanzarVueltaItem($id, $slot);
                $lanzados[] = $id;
            }

            $this->info(($this->option('dry') ? 'DRY ' : '') . 'Scheduler: ' . count($lanzados)
                . ' vuelta(s) para items [' . implode(',', $lanzados) . '] en slots libres ' . implode(',', $freeSlots) . '.');

            return self::SUCCESS;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** ¿El slot (worktree) está libre? = su flock no lo tiene una vuelta viva. */
    private function slotFree(string $sid): bool
    {
        $path = self::RUNTIME . "/{$sid}.lock";
        $f = @fopen($path, 'c');
        if (! $f) {
            return false;
        }
        $free = flock($f, LOCK_EX | LOCK_NB);
        if ($free) {
            flock($f, LOCK_UN);
        }
        fclose($f);

        return $free;
    }

    /** Lanza vuelta.sh en modo por-item, detached, en el worktree del slot. */
    private function lanzarVueltaItem(int $itemId, int $slot): void
    {
        $script = base_path('deploy/circuito/vuelta.sh');
        $wt     = self::RUNTIME . "/wt-{$slot}";
        $sid    = "wt-{$slot}";
        $env    = sprintf('CIRCUITO_ITEM=%d CIRCUITO_WT=%s CIRCUITO_SID=%s', $itemId, escapeshellarg($wt), escapeshellarg($sid));
        $cmd    = "setsid nohup env {$env} " . escapeshellarg($script) . ' >/dev/null 2>&1 &';
        $p = Process::fromShellCommandline($cmd, base_path());
        $p->run();
    }
}
