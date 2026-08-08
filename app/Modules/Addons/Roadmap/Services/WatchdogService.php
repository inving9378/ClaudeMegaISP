<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * WATCHDOG del equipo de workers + auto-recuperación del supervisor (#334).
 *
 * POR QUÉ existe: el scheduler se murió en silencio (cron sin `cd`) y NADIE avisó hasta que Irving
 * vio "0 sesiones" por casualidad. Este watchdog detecta proactivamente cuando un worker (o el
 * scheduler) NO está trabajando debiendo, intenta SANAR solo (reap del item atascado + relanzar el
 * scheduler) de forma ACOTADA, lo AUDITA, y solo ESCALA a Irving si no puede.
 *
 * Reglas:
 *  - Distingue "ocioso porque no hay trabajo" (normal, NO alarma) de "ocioso/caído habiendo trabajo".
 *  - Auto-recuperación acotada: máx N intentos por causa → luego escala (sin loops de relanzamiento).
 *  - Respeta el kill switch (#342): si Irving pausó, el watchdog NO relanza nada.
 *  - Reusa la maquinaria existente: `trabajandoAhora()` (salud por slot), `ejecutablesParalelo()`
 *    (¿hay trabajo?), `schedulerBeatSecs()` (cron vivo), y el reap de en_progreso (#334 F1).
 *  - Solo dev; auditable; no toca prod.
 */
class WatchdogService
{
    /** Latido del propio watchdog (para saber que ESTE también corre). */
    public const BEAT_KEY = 'circuito_watchdog_beat';

    /** Alertas activas que la Torre muestra (JSON array). */
    public const ALERTAS_KEY = 'circuito_watchdog_alertas';

    /** Bitácora de recuperaciones/eventos (JSON array, capado). */
    public const LOG_KEY = 'circuito_watchdog_log';

    /** Prefijo de contadores de intentos consecutivos por causa (`...:scheduler`, `...:wt-3`). */
    public const INTENTOS_PREFIX = 'circuito_watchdog_intentos:';

    /** El scheduler se considera CAÍDO si su último latido supera esto (seg) — alineado con la Torre. */
    public const SCHEDULER_STALE_SEG = 180;

    /** Un worker "corriendo" con latido más frío que esto (seg) se considera COLGADO → reap. */
    public const WORKER_HUNG_SEG = 600;

    /** Máx intentos consecutivos de auto-recuperación por causa antes de ESCALAR a Irving. */
    public const MAX_INTENTOS = 3;

    /** Cuántos eventos de bitácora se conservan. */
    private const LOG_CAP = 60;

    public function __construct(private RoadmapCircuitoService $svc)
    {
    }

    /**
     * SALUD POR SLOT para la Torre (wt-1..wt-N): 🟢 trabajando · ⚪ esperando (idle normal) ·
     * 🔴 caído/atascado. PURO-LECTURA (no recupera nada) → seguro para www-data.
     */
    public function saludSlots(): array
    {
        $sesiones   = $this->svc->trabajandoAhora()['sesiones'] ?? [];
        $hayTrabajo = $this->hayTrabajo();
        $schedVivo  = $this->schedulerVivo();

        $out = [];
        foreach ($sesiones as $s) {
            $out[] = $this->clasificaSlot($s, $hayTrabajo, $schedVivo);
        }

        return $out;
    }

    /** Estado agregado del watchdog para la Torre (salud + alertas + latido propio). */
    public function estado(): array
    {
        $beat = $this->getInt(self::BEAT_KEY);

        return [
            'slots'          => $this->saludSlots(),
            'alertas'        => $this->alertas(),
            'watchdog_vivo'  => $beat !== null && (time() - $beat) < self::SCHEDULER_STALE_SEG,
            'watchdog_secs'  => $beat !== null ? time() - $beat : null,
            'scheduler_vivo' => $this->schedulerVivo(),
            'hay_trabajo'    => $this->hayTrabajo(),
        ];
    }

    /**
     * PASADA DEL WATCHDOG (la corre `circuito:watchdog` por cron como meganet). Detecta anomalías,
     * intenta sanar (acotado), audita y escala. Devuelve un resumen de lo que hizo.
     */
    public function revisar(): array
    {
        $this->putInt(self::BEAT_KEY, time());

        // Kill switch (#342): en pausa el watchdog NO relanza nada (pausa intencional ≠ anomalía).
        if ($this->svc->isPaused()) {
            $this->setAlertas([]);              // limpia alertas viejas: nada está "caído", está pausado
            $this->resetTodosLosIntentos();
            return ['pausado' => true, 'acciones' => [], 'alertas' => []];
        }

        $acciones = [];
        $alertas  = [];

        $hayTrabajo = $this->hayTrabajo();
        $schedVivo  = $this->schedulerVivo();
        $sesiones   = $this->svc->trabajandoAhora()['sesiones'] ?? [];

        // ── (1) SCHEDULER CAÍDO (cron muerto) — el caso que acaba de pasar ──────────────────────
        if (! $schedVivo) {
            $n = $this->bump('scheduler');
            if ($n <= self::MAX_INTENTOS) {
                $this->dispararScheduler();     // relanza: reclama + lanza en slots libres
                $acciones[] = $this->audita('scheduler_relanzado',
                    "Scheduler sin latir ({$this->schedulerSecsTxt()}) → relanzado (intento {$n}/" . self::MAX_INTENTOS . ').');
            } else {
                $alertas[] = [
                    'tipo'     => 'scheduler_caido',
                    'sid'      => null,
                    'intentos' => $n,
                    'detalle'  => "El scheduler no late ({$this->schedulerSecsTxt()}) tras {$n} intentos de relanzarlo. "
                        . 'Revisa el cron (¿la línea `circuito:scheduler` tiene `cd /var/www/megaisp`?) o el proceso.',
                    'desde'    => now()->toIso8601String(),
                ];
            }
        } else {
            $this->reset('scheduler');
        }

        // ── (2) POR SLOT: workers colgados ──────────────────────────────────────────────────────
        foreach ($sesiones as $s) {
            $sid   = (string) ($s['sid'] ?? '');
            $clave = $this->svc->normalizaSid($sid);
            if (! $clave) {
                continue;   // sesiones legacy sin forma wt-K
            }

            $running    = (bool) ($s['running'] ?? false);
            $sinceBeat  = (int) ($s['segundos_desde_latido'] ?? 0);

            // Worker COLGADO: dice "corriendo" pero su latido lleva frío mucho tiempo (proceso muerto
            // sin cerrar la vuelta, o item que no avanza). Libera su item (reap) para desbloquear el
            // módulo; el scheduler relanza el slot si el proceso ya no lo tiene tomado.
            if ($running && $sinceBeat > self::WORKER_HUNG_SEG) {
                $n = $this->bump($clave);
                if ($n <= self::MAX_INTENTOS) {
                    $lib = $this->reapItemDeSlot($clave);
                    $acciones[] = $this->audita('worker_colgado_reap',
                        "{$clave} colgado (latido frío {$sinceBeat}s)" . ($lib ? " → item #{$lib} liberado (re-encolado)" : ' → sin item que liberar')
                        . "; el scheduler relanzará el slot (intento {$n}/" . self::MAX_INTENTOS . ').');
                    $this->dispararScheduler();
                } else {
                    $alertas[] = [
                        'tipo'     => 'worker_colgado',
                        'sid'      => $clave,
                        'intentos' => $n,
                        'detalle'  => "{$clave} lleva colgado (latido frío) tras {$n} intentos de recuperación. "
                            . 'El proceso podría seguir vivo reteniendo el slot — quizá haya que matarlo a mano (verifica el PID).',
                        'desde'    => now()->toIso8601String(),
                    ];
                }
            } elseif ($running && $sinceBeat <= self::WORKER_HUNG_SEG) {
                $this->reset($clave);   // late bien → sano
            }
            // idle: no se actúa por-slot (lo cubre el nivel scheduler).
        }

        // ── (3) OCIOSO CON TRABAJO pese a scheduler "vivo": vive pero NO despacha ────────────────
        $algunoCorriendo = collect($sesiones)->contains(fn ($s) => ! empty($s['running']));
        if ($hayTrabajo && ! $algunoCorriendo && $schedVivo) {
            $n = $this->bump('sin_despacho');
            if ($n <= self::MAX_INTENTOS) {
                $this->dispararScheduler();
                $acciones[] = $this->audita('sin_despacho_relanzado',
                    "Hay trabajo en cola y 0 workers corriendo aunque el scheduler late → relanzado (intento {$n}/" . self::MAX_INTENTOS . ').');
            } else {
                $alertas[] = [
                    'tipo'     => 'sin_despacho',
                    'sid'      => null,
                    'intentos' => $n,
                    'detalle'  => "Hay trabajo elegible y ningún worker lo toma pese a que el scheduler late, tras {$n} intentos. "
                        . 'Posible atasco del pool o de los locks de slot.',
                    'desde'    => now()->toIso8601String(),
                ];
            }
        } else {
            $this->reset('sin_despacho');
        }

        $this->setAlertas($alertas);

        if ($alertas) {
            Log::channel('roadmap_externo')->warning('watchdog-escala', ['alertas' => $alertas]);
        }

        return ['pausado' => false, 'acciones' => $acciones, 'alertas' => $alertas];
    }

    // ── Helpers de clasificación ────────────────────────────────────────────────────────────────

    /** Clasifica un slot: 🟢 trabajando · ⚪ esperando (idle normal) · 🔴 caído/atascado. */
    private function clasificaSlot(array $s, bool $hayTrabajo, bool $schedVivo): array
    {
        $sid       = (string) ($s['sid'] ?? '');
        $running   = (bool) ($s['running'] ?? false);
        $idle      = (bool) ($s['idle'] ?? false);
        $stale     = (bool) ($s['stale'] ?? false);
        $sinceBeat = (int) ($s['segundos_desde_latido'] ?? 0);

        $estado = 'esperando';
        $detalle = 'Ocioso — no hay trabajo elegible en cola (normal).';

        if ($running && ! $stale) {
            $estado  = 'trabajando';
            $detalle = 'Trabajando' . (! empty($s['item']['id']) ? " en #{$s['item']['id']}" : '') . '.';
        } elseif ($running && $stale) {
            $estado  = $sinceBeat > self::WORKER_HUNG_SEG ? 'caido' : 'esperando';
            $detalle = $sinceBeat > self::WORKER_HUNG_SEG
                ? "Colgado: latido frío {$sinceBeat}s con la vuelta abierta."
                : "Latido frío ({$sinceBeat}s), vigilando.";
        } elseif ($idle) {
            // Idle CON trabajo y scheduler caído = problema (no debería estar ocioso).
            if ($hayTrabajo && ! $schedVivo) {
                $estado  = 'caido';
                $detalle = 'Ocioso habiendo trabajo y con el scheduler sin latir → debería estar trabajando.';
            } elseif ($hayTrabajo) {
                $estado  = 'esperando';
                $detalle = 'Ocioso; hay trabajo — el scheduler lo tomará en la próxima vuelta.';
            }
        }

        return ['sid' => $sid, 'estado' => $estado, 'detalle' => $detalle];
    }

    private function hayTrabajo(): bool
    {
        return count($this->svc->ejecutablesParalelo($this->svc->modulosEnVuelo(), $this->svc->getParalelismo())) > 0;
    }

    private function schedulerVivo(): bool
    {
        $s = $this->svc->schedulerBeatSecs();

        return $s !== null && $s < self::SCHEDULER_STALE_SEG;
    }

    private function schedulerSecsTxt(): string
    {
        $s = $this->svc->schedulerBeatSecs();

        return $s === null ? 'nunca' : "{$s}s";
    }

    // ── Recuperación ────────────────────────────────────────────────────────────────────────────

    /** Relanza el scheduler (reclama + lanza en slots libres). Detached; su propio flock lo serializa. */
    private function dispararScheduler(): void
    {
        $cmd = 'setsid nohup php artisan circuito:scheduler >/dev/null 2>&1 &';
        $p = Process::fromShellCommandline($cmd, base_path());
        $p->setTimeout(20);
        try {
            $p->run();
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->error('watchdog-disparo-scheduler', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Libera (reap) el item que el slot dejó atascado en en_progreso — mismo criterio que
     * `circuito:reap-stuck` (#341: jamás toca en_desarrollo_humano). #561 — RE-ENCOLA vía
     * `RoadmapCircuitoService::reencolarHuerfano` en vez de escalar directo a la bandeja de Irving
     * (solo escala tras el tope de reclamos fallidos del propio item). Devuelve el id liberado o null.
     */
    private function reapItemDeSlot(string $sid): ?int
    {
        $item = RoadmapItem::where('worker_sid', $sid)
            ->where('estado_aprobacion', 'en_progreso')
            ->where('en_desarrollo_humano', false)
            ->orderByDesc('updated_at')
            ->first();

        if (! $item) {
            return null;
        }

        $this->svc->reencolarHuerfano($item, 'watchdog', "{$sid} se colgó con el item en en_progreso");

        return (int) $item->id;
    }

    // ── Alertas + bitácora + contadores (settings key-value) ─────────────────────────────────────

    public function alertas(): array
    {
        return $this->getJson(self::ALERTAS_KEY, []);
    }

    private function setAlertas(array $alertas): void
    {
        $this->putJson(self::ALERTAS_KEY, array_values($alertas));
    }

    /** Bitácora reciente de recuperaciones (para auditar qué/cuándo/por qué). */
    public function bitacora(int $limit = 30): array
    {
        return array_slice($this->getJson(self::LOG_KEY, []), 0, max(1, $limit));
    }

    /** Registra un evento de recuperación (audita) y devuelve el texto para el resumen. */
    private function audita(string $tipo, string $detalle): string
    {
        $log = $this->getJson(self::LOG_KEY, []);
        array_unshift($log, ['at' => now()->toIso8601String(), 'tipo' => $tipo, 'detalle' => $detalle]);
        $this->putJson(self::LOG_KEY, array_slice($log, 0, self::LOG_CAP));

        return $detalle;
    }

    /** Sube el contador de intentos de una causa y lo devuelve. */
    private function bump(string $causa): int
    {
        $n = $this->getInt(self::INTENTOS_PREFIX . $causa) ?? 0;
        $n++;
        $this->putInt(self::INTENTOS_PREFIX . $causa, $n);

        return $n;
    }

    private function reset(string $causa): void
    {
        DB::table('settings')->where('key', self::INTENTOS_PREFIX . $causa)->delete();
    }

    private function resetTodosLosIntentos(): void
    {
        DB::table('settings')->where('key', 'like', self::INTENTOS_PREFIX . '%')->delete();
    }

    // ── settings helpers (este servicio maneja sus propias llaves) ───────────────────────────────

    private function getInt(string $key): ?int
    {
        $v = DB::table('settings')->where('key', $key)->value('value');

        return $v === null ? null : (int) $v;
    }

    private function putInt(string $key, int $val): void
    {
        $this->put($key, (string) $val);
    }

    private function getJson(string $key, $default)
    {
        $v = DB::table('settings')->where('key', $key)->value('value');
        if ($v === null) {
            return $default;
        }
        $d = json_decode((string) $v, true);

        return is_array($d) ? $d : $default;
    }

    private function putJson(string $key, array $val): void
    {
        $this->put($key, json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function put(string $key, string $val): void
    {
        if (DB::table('settings')->where('key', $key)->exists()) {
            DB::table('settings')->where('key', $key)->update(['value' => $val, 'updated_at' => now()]);
        } else {
            DB::table('settings')->insert(['key' => $key, 'value' => $val, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
