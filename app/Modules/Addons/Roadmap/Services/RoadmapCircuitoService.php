<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio ÚNICA de la Hoja de Ruta para el Circuito de Mejora Continua.
 *
 * Extraída de RoadmapExternalController (sub-paso 1 del conector MCP) para que
 * TANTO la API externa token-en-path COMO el conector MCP (RoadmapMcpController)
 * compartan EXACTAMENTE las mismas queries, serialización, allowlist de escritura y
 * guards server-side — sin duplicar reglas (CLAUDE.md: "servicios compartidos únicos,
 * prohibido duplicar").
 *
 * Devuelve SIEMPRE arrays/modelos (nunca JsonResponse): el transporte (HTTP JSON o
 * JSON-RPC del MCP) lo decide cada llamador.
 */
class RoadmapCircuitoService
{
    /** Llave del kill switch en la tabla key-value `settings`. */
    public const PAUSE_KEY = 'circuito_pausado';

    /** Llave del modo de ejecución del circuito. */
    public const MODO_KEY = 'circuito_modo';

    public const MODOS = ['aviso_previo', 'autonomo'];

    /** Llave del modo de integración de ramas (#325). */
    public const MODO_INTEGRACION_KEY = 'circuito_modo_integracion';

    public const MODOS_INTEGRACION = ['auto-merge', 'revisar-y-mergear'];

    /**
     * Estado EN VIVO de la vuelta actual (#335), espejeado en `settings` como un solo
     * JSON. Es el canal compartido: lo ESCRIBE el ejecutor on-box (usuario meganet, que
     * SÍ puede leer el log en /home/meganet) y lo LEE la Torre (php-fpm = www-data, que
     * NO puede leer ese log por permisos). Forma:
     *   { started_at, heartbeat_at, finished, log_path, log_tail, current_item }
     */
    public const LIVE_KEY = 'circuito_live';

    /** Latido más frío que esto (seg) con la vuelta "corriendo" ⇒ posible circuito caído. */
    public const HEARTBEAT_STALE_SEG = 90;

    /** Cuántas líneas del final del log se espejean a BD para el panel "Ver log en vivo". */
    private const LOG_TAIL_LINES = 60;

    /**
     * Flag de DISPARO manual pendiente (#337): lo escribe la Torre (www-data) y lo consume
     * el picker on-box (meganet). Un SOLO flag ⇒ debounce natural (N disparos en la ventana
     * colapsan a una vuelta). JSON: { requested_at, by, origin, item_id }.
     */
    public const DISPARO_KEY = 'circuito_disparo_pendiente';

    public function find(int $id): ?RoadmapItem
    {
        return RoadmapItem::find($id);
    }

    /**
     * KILL SWITCH del Circuito. `true` = pausado: el ejecutor NO debe ejecutar nada
     * (sigue leyendo/reportando). Persistido en `settings` para que lo respeten por
     * igual la Torre de control (botón), la API externa y el conector MCP.
     */
    public function isPaused(): bool
    {
        return (string) DB::table('settings')->where('key', self::PAUSE_KEY)->value('value') === '1';
    }

    /**
     * Escribe el KILL SWITCH. #342 (seguridad): SOLO desde la Torre — un humano autenticado
     * (HTTP) con permiso `circuito.pause`. El ejecutor on-box corre por CLI SIN sesión, así
     * que esta puerta lo bloquea: para él el flag es SOLO-LECTURA (`isPaused`). Si está en 1
     * debe ABORTAR la vuelta, nunca cambiarlo. Cualquier intento fuera del contexto UI lanza.
     */
    public function setPaused(bool $paused): void
    {
        if (! (auth()->check() && auth()->user()->can('circuito.pause'))) {
            throw new \RuntimeException(
                'circuito_pausado es de solo-lectura fuera de la Torre: el kill switch solo lo '
                . 'cambia un humano autenticado con permiso circuito.pause (el ejecutor no puede).'
            );
        }
        $this->putSetting(self::PAUSE_KEY, $paused ? '1' : '0');
    }

    /**
     * Modo de ejecución del ejecutor on-box: `aviso_previo` (default; solo propone en
     * comentarios_claude, no ejecuta código) | `autonomo` (ejecuta A/B en su rama con
     * verificación). Un valor no reconocido cae a `aviso_previo` (falla-seguro).
     */
    public function getModo(): string
    {
        $v = (string) DB::table('settings')->where('key', self::MODO_KEY)->value('value');
        return in_array($v, self::MODOS, true) ? $v : 'aviso_previo';
    }

    public function setModo(string $modo): void
    {
        if (! in_array($modo, self::MODOS, true)) {
            $modo = 'aviso_previo';
        }
        $this->putSetting(self::MODO_KEY, $modo);
    }

    /**
     * Modo de integración de ramas (#325): `auto-merge` (default; A/B se integran solas al
     * verificar) | `revisar-y-mergear` (las ramas ESPERAN el ✓ de Irving en la Torre).
     * Valor no reconocido → `auto-merge` (comportamiento actual).
     */
    public function getModoIntegracion(): string
    {
        $v = (string) DB::table('settings')->where('key', self::MODO_INTEGRACION_KEY)->value('value');
        return in_array($v, self::MODOS_INTEGRACION, true) ? $v : 'auto-merge';
    }

    public function setModoIntegracion(string $modo): void
    {
        if (! in_array($modo, self::MODOS_INTEGRACION, true)) {
            $modo = 'auto-merge';
        }
        $this->putSetting(self::MODO_INTEGRACION_KEY, $modo);
    }

    private function putSetting(string $key, string $val): void
    {
        if (DB::table('settings')->where('key', $key)->exists()) {
            DB::table('settings')->where('key', $key)->update(['value' => $val, 'updated_at' => now()]);
        } else {
            DB::table('settings')->insert(['key' => $key, 'value' => $val, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Panorama global (todos los items, sin filtrar) — conteos por estado y por nivel. */
    public function resumen(): array
    {
        return [
            'total'      => RoadmapItem::count(),
            'por_estado' => RoadmapItem::selectRaw('estado_aprobacion, count(*) as n')
                ->groupBy('estado_aprobacion')->pluck('n', 'estado_aprobacion'),
            'por_nivel'  => RoadmapItem::selectRaw("COALESCE(nivel_riesgo,'sin_clasificar') as nivel, count(*) as n")
                ->groupBy('nivel')->pluck('n', 'nivel'),
            // Kill switch expuesto al ejecutor (la Rutina lo consulta para no ejecutar en pausa).
            'circuito_pausado' => $this->isPaused(),
        ];
    }

    /**
     * Lista compacta filtrada + paginada. Devuelve el NÚCLEO del payload
     * (generated_at, filtros_aplicados, meta, items) — sin resumen/leyenda/ayuda,
     * que son presentación del llamador.
     */
    public function listar(?string $estado, ?string $nivel, ?string $modulo, int $page, int $perPage): array
    {
        $q = RoadmapItem::query();
        if ($estado) $q->where('estado_aprobacion', $estado);
        if ($nivel)  $q->where('nivel_riesgo', $nivel);
        if ($modulo !== null && $modulo !== '') $q->where('modulo', 'like', '%' . $modulo . '%');

        $total = (clone $q)->count();
        $items = $q->ordered()->forPage($page, $perPage)->get()->map(fn ($i) => $this->compact($i));

        $filtros = array_filter(
            ['estado' => $estado, 'nivel' => $nivel, 'modulo' => $modulo],
            fn ($val) => $val !== null && $val !== ''
        );

        return [
            'generated_at'      => now()->toIso8601String(),
            'filtros_aplicados' => (object) $filtros,
            'meta'              => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int) ceil(($total ?: 0) / $perPage),
                'count'       => $items->count(),
            ],
            'items'             => $items,
        ];
    }

    /** Fila compacta (sin description/prompt) — para listas. El detalle va por serialize(). */
    public function compact(RoadmapItem $i): array
    {
        return [
            'id'                => $i->id,
            'title'             => $i->title,
            'modulo'            => $i->modulo,
            'nivel_riesgo'      => $i->nivel_riesgo,
            'estado_aprobacion' => $i->estado_aprobacion,
            'priority'          => $i->priority,
            'status'            => $i->status,
            'urgente'           => (bool) $i->urgente,
        ];
    }

    /** Detalle completo de un item (incluye comentarios_claude + log). */
    public function serialize(RoadmapItem $i): array
    {
        return [
            'id'                 => $i->id,
            'title'              => $i->title,
            'modulo'             => $i->modulo,
            'description'        => $i->description,
            'status'             => $i->status,
            'priority'           => $i->priority,
            'nivel_riesgo'       => $i->nivel_riesgo,
            'estado_aprobacion'  => $i->estado_aprobacion,
            'target_version'     => $i->target_version,
            'prompt_para_claude' => $i->prompt,
            'comentarios_claude' => $i->comentarios_claude,
            'subtasks'           => $i->subtasks,
            'log'                => $i->log,
            'started_at'         => optional($i->started_at)->toIso8601String(),
            'completed_at'       => optional($i->completed_at)->toIso8601String(),
            'revisado_at'        => optional($i->revisado_at)->toIso8601String(),
            'aprobado_por'       => $i->aprobado_por,
            'created_at'         => optional($i->created_at)->toIso8601String(),
            'updated_at'         => optional($i->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Allowlist de validación de la escritura acotada (los ÚNICOS 3 campos escribibles).
     * Fuente única compartida por la API externa y el conector MCP.
     */
    public function writeFieldRules(): array
    {
        return [
            'estado_aprobacion'  => ['sometimes', 'string', 'in:' . implode(',', RoadmapItem::ESTADOS_APROBACION)],
            'nivel_riesgo'       => ['sometimes', 'nullable', 'string', 'in:' . implode(',', RoadmapItem::NIVELES_RIESGO)],
            'comentarios_claude' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * Candados server-side que ninguna vía externa puede saltar (hallazgos del auditor).
     * Devuelve el motivo del rechazo (string) o null si pasa.
     *
     *  a) NO degradar nivel_riesgo hacia menos restrictivo (A<B<C): solo endurecer.
     *  b) SOLO un item nivel A puede quedar 'aprobado_claude'. B, C y sin clasificar
     *     topan en 'requiere_irving'. Se evalúa contra el nivel EFECTIVO tras el update.
     */
    public function guard(RoadmapItem $item, array $data): ?string
    {
        // (0.a) aprobado_irving es aprobación HUMANA: SOLO el endpoint autenticado de Irving
        // (Torre de control) puede fijarlo. El ejecutor (vía externa/MCP) JAMÁS puede
        // otorgarse a sí mismo la aprobación humana.
        if (($data['estado_aprobacion'] ?? null) === 'aprobado_irving') {
            return "La vía externa/MCP no puede fijar 'aprobado_irving': la aprobación humana "
                . 'es exclusiva de Irving desde la Torre de control.';
        }

        // (0.b) KILL SWITCH: en pausa, NADIE puede aprobar/ejecutar (aprobado_claude).
        // Leer y reportar (comentarios_claude, requiere_irving, etc.) sigue permitido.
        if (($data['estado_aprobacion'] ?? null) === 'aprobado_claude' && $this->isPaused()) {
            return 'Circuito en PAUSA (kill switch activo): no se puede aprobar ni ejecutar '
                . '(aprobado_claude). Se permite leer y reportar (comentarios_claude, requiere_irving). '
                . 'Reactiva el circuito desde la Torre de control para volver a ejecutar.';
        }

        $rank = fn (?string $n): int => match ($n) {
            'A'     => 0,
            'B'     => 1,
            'C'     => 2,
            default => -1, // sin clasificar = lo menos restrictivo
        };

        // (a) No degradar el nivel de riesgo.
        if (array_key_exists('nivel_riesgo', $data) && $rank($data['nivel_riesgo']) < $rank($item->nivel_riesgo)) {
            return "La vía externa no puede degradar nivel_riesgo ({$item->nivel_riesgo} → "
                . ($data['nivel_riesgo'] ?? 'null') . "); solo puede endurecer (A→B→C).";
        }

        // Nivel efectivo tras aplicar este update.
        $nivelEfectivo = array_key_exists('nivel_riesgo', $data) ? $data['nivel_riesgo'] : $item->nivel_riesgo;

        // (b) Solo un item nivel A puede quedar aprobado_claude por esta vía.
        if (($data['estado_aprobacion'] ?? null) === 'aprobado_claude' && $nivelEfectivo !== 'A') {
            $n = $nivelEfectivo ?? 'sin clasificar';
            return "Solo un item nivel A puede quedar 'aprobado_claude' por la vía externa "
                . "(este es nivel {$n}); para B/C el máximo es 'requiere_irving'.";
        }

        return null;
    }

    /**
     * Aplica la escritura acotada: sella revisado_at + aprobado_por (autor según la vía)
     * y persiste. Devuelve el item fresco. El llamador YA validó (writeFieldRules) y pasó
     * el guard().
     */
    public function applyWrite(RoadmapItem $item, array $data, string $actor): RoadmapItem
    {
        $data['revisado_at']  = now();
        $data['aprobado_por'] = $actor;

        $item->update($data);

        return $item->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ESTADO EN VIVO DE LA VUELTA (#335) — heartbeat + log espejeado por BD.
    // Las escrituras (liveStart/liveBeat/liveEnd) las llama el wrapper como meganet.
    // Las lecturas (liveState/liveLogTail) las llama la Torre como www-data.
    // ─────────────────────────────────────────────────────────────────────────

    /** Lee el JSON crudo de estado en vivo (o null si no hay). */
    private function readLive(): ?array
    {
        $raw = DB::table('settings')->where('key', self::LIVE_KEY)->value('value');
        if (! $raw) {
            return null;
        }
        $d = json_decode((string) $raw, true);

        return is_array($d) ? $d : null;
    }

    private function writeLive(array $d): void
    {
        $this->putSetting(self::LIVE_KEY, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Marca el ARRANQUE de una vuelta (lo llama el wrapper como meganet). */
    public function liveStart(string $logPath): void
    {
        $now = time();
        $this->writeLive([
            'started_at'   => $now,
            'heartbeat_at' => $now,
            'finished'     => false,
            'log_path'     => $logPath,
            'log_tail'     => $this->tailFile($logPath),
            'current_item' => null,
        ]);
    }

    /** Latido: refresca heartbeat + tail del log + #item en curso (best-effort). */
    public function liveBeat(?string $logPath = null): void
    {
        $d = $this->readLive() ?? [];
        $log = $logPath ?: ($d['log_path'] ?? null);

        $d['heartbeat_at'] = time();
        // Un latido SIEMPRE significa "viva": el --watch solo corre entre --start y --end.
        // Forzar finished=false lo hace auto-sanable (si algo dejó finished=true colgado).
        $d['finished']     = false;
        if ($log) {
            $tail = $this->tailFile($log);
            $d['log_path']     = $log;
            $d['log_tail']     = $tail;
            $d['current_item'] = $this->parseCurrentItem($tail) ?? ($d['current_item'] ?? null);
        }

        $this->writeLive($d);
    }

    /** Marca el FIN de la vuelta (deja de reportar "corriendo"). */
    public function liveEnd(): void
    {
        $d = $this->readLive();
        if (! $d) {
            return;
        }
        $d['finished']     = true;
        $d['heartbeat_at'] = time();
        if (! empty($d['log_path'])) {
            $d['log_tail'] = $this->tailFile($d['log_path']);
        }
        $this->writeLive($d);
    }

    /**
     * Estado derivado para la Torre. PURO-LECTURA de BD (no toca archivos) → seguro para
     * www-data. `running` = arrancó y no ha terminado; `stale` = corriendo pero el latido
     * se enfrió (posible circuito caído).
     */
    public function liveState(): array
    {
        $d   = $this->readLive();
        $now = time();

        if (! $d || empty($d['started_at'])) {
            return ['running' => false, 'stale' => false, 'started_at' => null];
        }

        $finished  = (bool) ($d['finished'] ?? false);
        $hb        = (int) ($d['heartbeat_at'] ?? 0);
        $sinceBeat = max(0, $now - $hb);
        $running   = ! $finished;

        return [
            'running'               => $running,
            'finished'              => $finished,
            'stale'                 => $running && $sinceBeat > self::HEARTBEAT_STALE_SEG,
            'started_at'            => Carbon::createFromTimestamp((int) $d['started_at'])->toIso8601String(),
            'heartbeat_at'          => $hb ? Carbon::createFromTimestamp($hb)->toIso8601String() : null,
            'segundos_desde_latido' => $sinceBeat,
            'segundos_corriendo'    => $running ? max(0, $now - (int) $d['started_at']) : null,
            'current_item'          => isset($d['current_item']) ? ($d['current_item'] ?: null) : null,
        ];
    }

    /** Tail del log en vivo (espejo en BD) para el panel "Ver log en vivo". */
    public function liveLogTail(): string
    {
        return (string) (($this->readLive() ?? [])['log_tail'] ?? '');
    }

    /**
     * Próxima vuelta estimada a partir del intervalo del cron (config `circuito.interval_min`,
     * ESPEJO del crontab cada-30-min). No controla el cron real; solo informa a la Torre.
     */
    public function proximaVueltaAt(): string
    {
        $min = (int) config('circuito.interval_min', 30);
        if ($min < 1 || $min > 60) {
            $min = 30;
        }

        $now     = Carbon::now()->second(0);
        $nextMin = (intdiv($now->minute, $min) + 1) * $min;

        $next = $now->copy();
        if ($nextMin >= 60) {
            $next->addHour()->minute($nextMin - 60);
        } else {
            $next->minute($nextMin);
        }

        return $next->toIso8601String();
    }

    /** Últimas N líneas de un archivo de log (logs de vuelta son pequeños, ~pocos KB). */
    private function tailFile(string $path, int $lines = self::LOG_TAIL_LINES): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }
        $f = @file($path, FILE_IGNORE_NEW_LINES);
        if ($f === false) {
            return '';
        }

        return implode("\n", array_slice($f, -$lines));
    }

    /** Extrae el #item más reciente mencionado en el tail (best-effort, para "tocando #NNN"). */
    private function parseCurrentItem(string $tail): ?int
    {
        if (preg_match_all('/#(\d{1,6})\b/', $tail, $m) && ! empty($m[1])) {
            return (int) end($m[1]);
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DISPARO MANUAL / INMEDIATO (#337) — flag en BD + auditoría.
    // La Torre (www-data) SOLICITA; el picker on-box (meganet) CONSUME y lanza vuelta.sh.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Solicita una vuelta inmediata. Decisión de Irving: EN PAUSA se BLOQUEA (no encola).
     * Si ya corre una vuelta, el flag queda pendiente y el picker la dispara al terminar
     * (= "encola", nunca solapa). `origin` = 'boton' | 'urgente'.
     */
    public function requestDisparo(string $by, string $origin = 'boton', ?int $itemId = null): array
    {
        if ($this->isPaused()) {
            return [
                'ok'      => false,
                'motivo'  => 'pausado',
                'mensaje' => 'El circuito está en pausa (kill switch). Reanúdalo para poder ejecutar.',
            ];
        }

        $origin = in_array($origin, ['boton', 'urgente'], true) ? $origin : 'boton';
        $now = now();

        // Un solo flag = debounce natural (N disparos en la ventana → 1 vuelta).
        $this->putSetting(self::DISPARO_KEY, json_encode([
            'requested_at' => $now->timestamp,
            'by'           => $by,
            'origin'       => $origin,
            'item_id'      => $itemId,
        ], JSON_UNESCAPED_UNICODE));

        DB::table('circuito_disparos')->insert([
            'requested_by' => mb_substr($by, 0, 190),
            'origin'       => $origin,
            'item_id'      => $itemId,
            'requested_at' => $now,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $running = (bool) ($this->liveState()['running'] ?? false);

        return [
            'ok'           => true,
            'ya_corriendo' => $running,
            'mensaje'      => $running
                ? 'Ya hay una vuelta corriendo; tu disparo quedó encolado para la siguiente.'
                : 'Disparo encolado; la vuelta arranca en segundos.',
        ];
    }

    /** Lee el flag de disparo pendiente (o null). */
    public function pendingDisparo(): ?array
    {
        $raw = DB::table('settings')->where('key', self::DISPARO_KEY)->value('value');
        if (! $raw) {
            return null;
        }
        $d = json_decode((string) $raw, true);

        return is_array($d) ? $d : null;
    }

    /**
     * El picker CONSUME el flag: lo borra (atómico-suficiente para un único picker) y sella
     * consumed_at en la auditoría pendiente. Devuelve el flag consumido (o null si no había).
     */
    public function consumeDisparo(): ?array
    {
        $flag = $this->pendingDisparo();
        if (! $flag) {
            return null;
        }

        $this->clearDisparo();
        DB::table('circuito_disparos')->whereNull('consumed_at')->update(['consumed_at' => now()]);

        return $flag;
    }

    public function clearDisparo(): void
    {
        DB::table('settings')->where('key', self::DISPARO_KEY)->delete();
    }
}
