<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\MergeRunner;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    private const SETTING_DESTRABE_BEAT = 'circuito_destrabe_bandeja_beat';

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

                // TORRE V2 — vuelta de Thomas. Va ENGANCHADA aquí, y no en su propia línea de cron,
                // porque el scheduler ya es el único despachador (#432 B1) y corre cada minuto: un
                // cron paralelo abriría una segunda carrera sobre los mismos items.
                // Recoge consultas que quedaron colgadas (la terminal preguntó y se le acabó el
                // turno) y sella estimaciones. Best-effort: un fallo suyo no frena el reparto.
                try {
                    app(\App\Modules\Addons\Roadmap\Services\ThomasService::class)->tick();
                } catch (\Throwable $e) {
                    Log::channel('roadmap_externo')->warning('thomas-tick-fallo', ['error' => $e->getMessage()]);
                }

                // #547 — DESTRABE automático: ver docblock de tickDestrabe() abajo.
                $this->tickDestrabe();

                // #559 — MOTOR DE AUDITORÍA CONTINUA. El generador de trabajo va enganchado aquí,
                // igual que Thomas, y por el mismo motivo: el scheduler es el único despachador y
                // ya corre cada minuto; una línea de cron aparte abriría una segunda carrera sobre
                // los mismos items.
                //
                // Va ANTES de calcular slots libres a propósito: los items que genere quedan
                // disponibles para el reparto de ESTA MISMA vuelta, no de la siguiente.
                //
                // El propio servicio decide si toca correr (cola bajo el umbral + intervalo mínimo
                // + sus dos kill-switches), así que aquí no hay política: sólo la llamada.
                // Best-effort — que el generador falle nunca debe frenar el reparto.
                try {
                    $auditor = app(\App\Modules\Addons\Roadmap\Services\AuditorService::class);
                    if ($auditor->debeCorrer()['corre']) {
                        $r = $auditor->ciclo(true);
                        if ($r['creados']) {
                            Log::channel('roadmap_externo')->info('auditor-genero-trabajo', [
                                'creados' => count($r['creados']),
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::channel('roadmap_externo')->warning('auditor-fallo', ['error' => $e->getMessage()]);
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
                    // #507 sub-paso 3 — `claimed_at` sella el lease del slot (lo renueva el latido).
                    ->update(['estado_aprobacion' => 'en_progreso', 'worker_sid' => "wt-{$slot}",
                        'claimed_at' => now(), 'updated_at' => now()]);
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

    /**
     * #547 — DESTRABE automático de la bandeja: corre `circuito:destrabar-bandeja --apply`, que
     * auto-mergea lo verificado y reversible, auto-decide lo ya contestado/mecánico y consolida
     * lo estratégico (#566 E1/E2/E4). Antes de esto el comando existía pero nada lo llamaba —
     * items terminados + decididos se quedaban parqueados en `esperando_merge_irving` para
     * siempre. Va aquí, no en su propia línea de crontab, por la misma razón que Thomas y el
     * Auditor arriba: el scheduler ya es el único despachador (#432 B1); una línea aparte abriría
     * una segunda carrera sobre los mismos items. Throttle propio (config
     * `circuito.thomas.destrabe_bandeja`) para no re-escanear cada minuto sin necesidad — el cap
     * de auto-merges por CICLO ya acota el daño de cada corrida, esto solo acota la frecuencia.
     * Best-effort: un fallo aquí nunca debe frenar el reparto.
     */
    private function tickDestrabe(): void
    {
        if (! (bool) config('circuito.thomas.destrabe_bandeja.enabled', true)) {
            return;
        }

        $intervalo = max(1, (int) config('circuito.thomas.destrabe_bandeja.intervalo_minutos', 5));
        $ultima    = DB::table('settings')->where('key', self::SETTING_DESTRABE_BEAT)->value('value');
        if ($ultima !== null && (time() - (int) $ultima) < $intervalo * 60) {
            return;
        }

        try {
            Artisan::call('circuito:destrabar-bandeja', [
                '--apply' => true,
                '--limit' => (int) config('circuito.thomas.destrabe_bandeja.limit', 120),
            ]);
            DB::table('settings')->updateOrInsert(
                ['key' => self::SETTING_DESTRABE_BEAT],
                ['value' => (string) time(), 'updated_at' => now()]
            );
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->warning('destrabe-bandeja-fallo', ['error' => $e->getMessage()]);
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
