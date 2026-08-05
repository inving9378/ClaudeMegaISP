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

    /** Metadata de CUÁNDO/QUIÉN pausó (#343): JSON `{at, by}`. Solo existe mientras está en pausa. */
    public const PAUSE_META_KEY = 'circuito_pausado_meta';

    /** Llave del modo de ejecución del circuito. */
    public const MODO_KEY = 'circuito_modo';

    public const MODOS = ['aviso_previo', 'autonomo'];

    /** Llave del modo de integración de ramas (#325). */
    public const MODO_INTEGRACION_KEY = 'circuito_modo_integracion';

    public const MODOS_INTEGRACION = ['auto-merge', 'revisar-y-mergear'];

    /** Flag del agente REVISOR (#338): '1' = ON. Default OFF (arranque conservador). Control de Irving. */
    public const REVISOR_KEY = 'circuito_revisor';

    /**
     * #336: modelo del ejecutor CLI (`claude -p` en vuelta.sh) para la vuelta RUTINARIA
     * (leer/triagear/ejecutar A-B). Alias del CLI (ej. 'sonnet', 'opus'), no id completo de API.
     * Default 'sonnet' — cuida la cuota de Opus del plan Max para el uso interactivo.
     */
    public const MODELO_RUTINA_KEY = 'circuito_modelo_rutina';

    /**
     * #336: modelo reservado para razonamiento difícil (decisiones nivel C). El pase CLI en sí
     * siempre corre en rutina; el razonamiento difícil lo hace el REVISOR (#338, API directa,
     * `config('circuito.revisor.model_hard')`, ya en Opus) — esta llave queda como el equivalente
     * en alias-CLI, documentada y lista para un futuro pase `claude -p --model opus` si se necesita.
     */
    public const MODELO_DIFICIL_KEY = 'circuito_modelo_dificil';

    /**
     * #336 (Opción D — escape hatch): si se setea, PISA tanto rutina como difícil para el CLI.
     * Vacío/ausente = sin forzar (usa rutina/difícil normal). Control de Irving.
     */
    public const MODELO_FORZAR_KEY = 'circuito_modelo_forzar';

    /**
     * Voz (SpeechSynthesisVoice.name) elegida por el administrador para 🔊 Escuchar en la Torre
     * de Integración (#424). Vacío/ausente = automática (la Torre elige es-MX → es-* → default).
     */
    public const VOICE_KEY = 'circuito_tts_voice';

    /** Velocidad (SpeechSynthesisUtterance.rate) de 🔊 Escuchar (#424). Vacío/ausente = 1.0. Rango [0.5, 2.0]. */
    public const RATE_KEY = 'circuito_tts_rate';

    /**
     * Estado EN VIVO de una vuelta (#335), espejeado en `settings`. Es el canal compartido:
     * lo ESCRIBE el ejecutor on-box (usuario meganet, que SÍ puede leer el log en /home/meganet)
     * y lo LEE la Torre (php-fpm = www-data, que NO puede leer ese log por permisos). Forma:
     *   { started_at, heartbeat_at, finished, log_path, log_tail, current_item, fases, ... }
     *
     * #334 Fase 0 — estado POR SESIÓN: cada worktree/vuelta escribe su propia fila
     * `circuito_live:<sid>` (LIVE_PREFIX + sid). Antes era un blob único `circuito_live`; ahora
     * varias sesiones (paralelas en Fase 1) coexisten sin pisarse. `LIVE_KEY` queda como base
     * del prefijo y como llave LEGACY que se ignora/limpia en la transición.
     */
    public const LIVE_KEY = 'circuito_live';

    /** Prefijo de las filas de estado live por-sesión: `circuito_live:<sid>` (#334). */
    public const LIVE_PREFIX = 'circuito_live:';

    /** Una sesión TERMINADA se sigue mostrando este tiempo (seg) y luego se cae del visor. */
    public const LIVE_ENDED_RETAIN_SEG = 300;

    /** Latido más frío que esto (seg) con la vuelta "corriendo" ⇒ posible circuito caído. */
    public const HEARTBEAT_STALE_SEG = 90;

    /** Cuántas líneas del final del log se espejean a BD para el panel "Ver log en vivo". */
    private const LOG_TAIL_LINES = 60;

    /** Enum canónico de fases del ejecutor (orden natural del stepper del visor #349). */
    public const FASES = ['triage', 'decision', 'rama', 'editando', 'verificando', 'integrando'];

    /**
     * Flag de DISPARO manual pendiente (#337): lo escribe la Torre (www-data) y lo consume
     * el picker on-box (meganet). Un SOLO flag ⇒ debounce natural (N disparos en la ventana
     * colapsan a una vuelta). JSON: { requested_at, by, origin, item_id }.
     */
    public const DISPARO_KEY = 'circuito_disparo_pendiente';

    /**
     * Cola de MERGE a dev (#334 F0-fix). La Torre (www-data) NO puede escribir `.git` (objetos/refs
     * los creó el ejecutor=meganet, sin group-write para www-data), así que el merge NO lo hace
     * www-data: se ENCOLA aquí y lo ejecuta el runner on-box (meganet, en el checkout PRINCIPAL,
     * donde vive `main`) — mismo patrón que el disparo. FIFO de {item_id, by, trigger, at}.
     */
    public const MERGE_QUEUE_KEY = 'circuito_merge_cola';

    /** Resultado del último intento de merge por item: `circuito_merge_result:<id>` (para la UI). */
    public const MERGE_RESULT_PREFIX = 'circuito_merge_result:';

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

        // #343: sella cuándo/quién pausó (auditoría + salvaguarda de "pausa olvidada"). Al
        // reanudar se limpia — la meta solo es relevante mientras sigue en pausa.
        if ($paused) {
            $u = auth()->user();
            $this->putSetting(self::PAUSE_META_KEY, json_encode([
                'at' => now()->timestamp,
                'by' => 'irving:' . ($u->login_user ?? $u->email ?? $u->id ?? '?'),
            ], JSON_UNESCAPED_UNICODE));
        } else {
            DB::table('settings')->where('key', self::PAUSE_META_KEY)->delete();
        }
    }

    /**
     * Info de la pausa vigente para la salvaguarda "pausa olvidada" (#343): PURO-LECTURA,
     * NO reanuda nada — el kill switch lo sigue tocando solo un humano en la Torre. Devuelve
     * null si no está pausado. `aviso` = ya pasó el umbral configurable (default 3h).
     */
    public function pausedInfo(): ?array
    {
        if (! $this->isPaused()) {
            return null;
        }
        $raw  = DB::table('settings')->where('key', self::PAUSE_META_KEY)->value('value');
        $meta = $raw ? json_decode((string) $raw, true) : null;
        $at   = is_array($meta) ? (int) ($meta['at'] ?? 0) : 0;

        $horas   = $at > 0 ? round((time() - $at) / 3600, 1) : null;
        $umbral  = (float) config('circuito.pausa_aviso_horas', 3);

        return [
            'desde'       => $at > 0 ? Carbon::createFromTimestamp($at)->toIso8601String() : null,
            'por'         => is_array($meta) ? ($meta['by'] ?? null) : null,
            'horas'       => $horas,
            'aviso_horas' => $umbral,
            // Sin meta (pausada antes de este fix, o vía CLI legacy) → no se puede calcular
            // antigüedad; se avisa igual por precaución (mejor falso-positivo que pausa olvidada).
            'olvidada'    => $horas === null || $horas >= $umbral,
        ];
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

    /**
     * Agente REVISOR (#338): ¿está ON? Default OFF (arranque conservador). Cuando está ON, el
     * ejecutor consulta al revisor para sus B (dentro de alcance) y EJECUTA los `aprobado_revisor`.
     * Con OFF, el revisor no participa en el ciclo y los `aprobado_revisor` NO se ejecutan solos
     * (quedan marcados esperando que Irving encienda el flag). El ejecutor NUNCA debe escribirlo.
     */
    public function revisorEnabled(): bool
    {
        return (string) DB::table('settings')->where('key', self::REVISOR_KEY)->value('value') === '1';
    }

    /** Enciende/apaga el flag del revisor (control de Irving; el ejecutor no lo toca). */
    public function setRevisorEnabled(bool $on): void
    {
        $this->putSetting(self::REVISOR_KEY, $on ? '1' : '0');
    }

    /** Modelo (alias CLI) para la vuelta rutinaria del ejecutor. Vacío/ausente → 'sonnet'. */
    public function getModeloRutina(): string
    {
        $v = trim((string) DB::table('settings')->where('key', self::MODELO_RUTINA_KEY)->value('value'));
        return $v !== '' ? $v : 'sonnet';
    }

    public function setModeloRutina(string $modelo): void
    {
        $this->putSetting(self::MODELO_RUTINA_KEY, mb_substr(trim($modelo), 0, 40));
    }

    /** Modelo (alias CLI) reservado para razonamiento difícil (nivel C). Vacío/ausente → 'opus'. */
    public function getModeloDificil(): string
    {
        $v = trim((string) DB::table('settings')->where('key', self::MODELO_DIFICIL_KEY)->value('value'));
        return $v !== '' ? $v : 'opus';
    }

    public function setModeloDificil(string $modelo): void
    {
        $this->putSetting(self::MODELO_DIFICIL_KEY, mb_substr(trim($modelo), 0, 40));
    }

    /** Override que pisa rutina/difícil (Opción D, escape hatch). '' = sin forzar. */
    public function getModeloForzar(): string
    {
        return trim((string) DB::table('settings')->where('key', self::MODELO_FORZAR_KEY)->value('value'));
    }

    public function setModeloForzar(string $modelo): void
    {
        $this->putSetting(self::MODELO_FORZAR_KEY, mb_substr(trim($modelo), 0, 40));
    }

    /**
     * Resuelve el modelo (alias CLI) que debe usar el ejecutor en ESTA vuelta: `circuito_modelo_forzar`
     * pisa todo si está seteado; si no, rutina (el `claude -p` de vuelta.sh SIEMPRE es la vuelta
     * rutinaria — el razonamiento difícil de nivel C lo hace el REVISOR #338 por API, no este pase).
     */
    public function resolveModeloCli(): string
    {
        $forzar = $this->getModeloForzar();
        return $forzar !== '' ? $forzar : $this->getModeloRutina();
    }

    /** Voz guardada para 🔊 Escuchar (#424). Null = sin preferencia → la Torre usa su fallback es-MX. */
    public function getVozTts(): ?string
    {
        $v = (string) DB::table('settings')->where('key', self::VOICE_KEY)->value('value');
        return $v !== '' ? $v : null;
    }

    /** Persiste la voz elegida por el administrador. Null/'' = borrar preferencia (vuelve a automática). */
    public function setVozTts(?string $voz): void
    {
        $this->putSetting(self::VOICE_KEY, trim((string) $voz));
    }

    /** Velocidad (rate) de 🔊 Escuchar (#424). Default 1.0; se guarda acotada a [0.5, 2.0]. */
    public function getRateTts(): float
    {
        $v = (string) DB::table('settings')->where('key', self::RATE_KEY)->value('value');
        return $v !== '' ? $this->clampRate((float) $v) : 1.0;
    }

    /** Persiste la velocidad elegida por el administrador (acotada a [0.5, 2.0]). */
    public function setRateTts(float $rate): void
    {
        $this->putSetting(self::RATE_KEY, (string) $this->clampRate($rate));
    }

    private function clampRate(float $rate): float
    {
        return max(0.5, min(2.0, $rate));
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
            'estacion'          => $i->estacion,   // #432: intake|bandeja|listo|terminal|integracion|done
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
     *  c) (#260) El nivel A que habilita 'aprobado_claude' debe haber sido fijado por un
     *     actor INTERNO (Claude Code / Irving). Si ESTA misma escritura externa es la que
     *     sube/fija nivel_riesgo (aunque termine en A), o el item quedó en A por una subida
     *     externa previa (nivel_riesgo_origen='externo'), el máximo alcanzable es
     *     'requiere_irving' — el mismo actor externo no puede fijar el riesgo Y la
     *     aprobación de ejecución automática en el mismo lazo.
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

        // (0.a-bis) #338: 'aprobado_revisor' lo otorga SOLO el revisor interno on-box
        // (RevisorService). La vía externa/MCP no puede fijarlo (ni Cowork ni nadie de fuera).
        if (($data['estado_aprobacion'] ?? null) === 'aprobado_revisor') {
            return "La vía externa/MCP no puede fijar 'aprobado_revisor': lo otorga exclusivamente "
                . 'el revisor adversarial interno on-box (#338).';
        }

        // (0.b) KILL SWITCH: en pausa, NADIE puede aprobar/ejecutar (aprobado_claude / aprobado_revisor).
        // Leer y reportar (comentarios_claude, requiere_irving, etc.) sigue permitido.
        if (in_array($data['estado_aprobacion'] ?? null, ['aprobado_claude', 'aprobado_revisor'], true) && $this->isPaused()) {
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

        // (c) #260: el A que habilita aprobado_claude debe venir de origen interno. Si esta
        // MISMA escritura toca nivel_riesgo, applyWrite() la va a sellar como 'externo' →
        // origen efectivo = externo. Si no la toca, el origen efectivo es el ya persistido.
        if (($data['estado_aprobacion'] ?? null) === 'aprobado_claude') {
            $origenEfectivo = array_key_exists('nivel_riesgo', $data) ? 'externo' : ($item->nivel_riesgo_origen ?? 'interno');
            if ($origenEfectivo !== 'interno') {
                return "El nivel A de este item fue fijado/subido por la vía externa: no puede "
                    . "quedar 'aprobado_claude' en el mismo lazo (el mismo actor no puede fijar "
                    . "nivel_riesgo Y la aprobación). Máximo alcanzable: 'requiere_irving'.";
            }
        }

        return null;
    }

    /**
     * Aplica la escritura acotada: sella revisado_at + aprobado_por (autor según la vía)
     * y persiste. Devuelve el item fresco. El llamador YA validó (writeFieldRules) y pasó
     * el guard(). #260: toda llamada a applyWrite() viene de una vía EXTERNA (Cowork/MCP,
     * únicos consumidores) → si toca nivel_riesgo, sella nivel_riesgo_origen='externo'
     * para que el guard() lo detecte en escrituras futuras.
     */
    public function applyWrite(RoadmapItem $item, array $data, string $actor): RoadmapItem
    {
        $data['revisado_at']  = now();
        $data['aprobado_por'] = $actor;

        if (array_key_exists('nivel_riesgo', $data)) {
            $data['nivel_riesgo_origen'] = 'externo';
        }

        $item->update($data);

        return $item->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ESTADO EN VIVO DE LA VUELTA (#335) — heartbeat + log espejeado por BD.
    // Las escrituras (liveStart/liveBeat/liveEnd) las llama el wrapper como meganet.
    // Las lecturas (liveState/liveLogTail) las llama la Torre como www-data.
    // ─────────────────────────────────────────────────────────────────────────

    /** Lee el JSON crudo de estado en vivo de UNA sesión (o null si no hay). */
    private function readLive(string $sid): ?array
    {
        $raw = DB::table('settings')->where('key', self::LIVE_PREFIX . $sid)->value('value');
        if (! $raw) {
            return null;
        }
        $d = json_decode((string) $raw, true);

        return is_array($d) ? $d : null;
    }

    private function writeLive(string $sid, array $d): void
    {
        $this->putSetting(self::LIVE_PREFIX . $sid, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Todas las sesiones live vivas o recién terminadas: [sid => data]. Descarta las TERMINADAS
     * cuyo último latido excede LIVE_ENDED_RETAIN_SEG (ya no aportan al visor). Solo lee filas
     * `circuito_live:<sid>` (ignora la llave legacy `circuito_live` sin sufijo).
     */
    /**
     * IDs de items que AHORA MISMO toca alguna terminal viva (current_item de cada sesión live).
     * Fuente única para excluirlos del backlog de la Hoja de ruta (pipeline por estado).
     */
    public function idsEnCurso(): array
    {
        $ids = [];
        foreach ($this->allLiveSessions() as $d) {
            $cur = $d['current_item'] ?? null;
            if ($cur) {
                $ids[] = (int) $cur;
            }
        }

        return array_values(array_unique($ids));
    }

    private function allLiveSessions(): array
    {
        $rows = DB::table('settings')
            ->where('key', 'like', self::LIVE_PREFIX . '%')
            ->get(['key', 'value']);

        $now = time();
        $out = [];
        foreach ($rows as $row) {
            $sid = substr($row->key, strlen(self::LIVE_PREFIX));
            if ($sid === '') {
                continue;
            }
            $d = json_decode((string) $row->value, true);
            if (! is_array($d) || empty($d['started_at'])) {
                continue;
            }
            $finished = (bool) ($d['finished'] ?? false);
            $hb       = (int) ($d['heartbeat_at'] ?? 0);
            if ($finished && ($now - $hb) > self::LIVE_ENDED_RETAIN_SEG) {
                continue; // terminada hace rato → fuera del visor
            }
            $out[$sid] = $d;
        }

        return $out;
    }

    /** Marca el ARRANQUE de una vuelta de la sesión $sid (lo llama el wrapper como meganet). */
    public function liveStart(string $sid, string $logPath): void
    {
        // Transición #334: limpia la llave legacy `circuito_live` (blob único sin sufijo) la
        // primera vez que arranca una sesión con sufijo, para que no quede colgada en el visor.
        DB::table('settings')->where('key', self::LIVE_KEY)->delete();

        $now  = time();
        $prev = $this->readLive($sid) ?? [];
        $this->writeLive($sid, [
            'started_at'   => $now,
            'heartbeat_at' => $now,
            'finished'     => false,
            'log_path'     => $logPath,
            'log_tail'     => $this->tailFile($logPath),
            'current_item' => null,
            'fases'        => [],   // migas CIRCUITO_FASE de ESTA vuelta (#349)
            'artefactos'   => [],   // rama/commits/archivos best-effort de ESTA vuelta (#349)
            // El resumen (CIRCUITO_META) de la vuelta ANTERIOR se conserva visible hasta que
            // esta vuelta cierre con el suyo (#349: "queda visible hasta la siguiente").
            'meta'         => $prev['meta'] ?? null,
        ]);
    }

    /** Latido: refresca heartbeat + tail del log + #item en curso (best-effort). */
    public function liveBeat(string $sid, ?string $logPath = null): void
    {
        $d = $this->readLive($sid) ?? [];
        $log = $logPath ?: ($d['log_path'] ?? null);

        $d['heartbeat_at'] = time();
        // Un latido SIEMPRE significa "viva": el --watch solo corre entre --start y --end.
        // Forzar finished=false lo hace auto-sanable (si algo dejó finished=true colgado).
        $d['finished']     = false;
        if ($log) {
            $tail = $this->tailFile($log);
            $d['log_path']     = $log;
            $d['log_tail']     = $tail;
            // Fases y artefactos se parsean del log COMPLETO (no del tail) para no perder los
            // pasos tempranos cuando el tail se desplaza. Corre como meganet (lee el archivo). (#349)
            $lines             = $this->readLogLines($log);
            $d['fases']        = $this->parseFases($lines, (array) ($d['fases'] ?? []));
            $d['artefactos']   = $this->parseArtefactos($lines);
            $d['current_item'] = $this->lastFaseItem($d['fases'])
                ?? $this->parseCurrentItem($tail)
                ?? ($d['current_item'] ?? null);
        }

        $this->writeLive($sid, $d);

        // #507 sub-paso 3 — el latido RENUEVA el lease del item que trabaja este worker. Se escribe
        // con un UPDATE crudo a propósito: NO toca `updated_at`, para que "sigo vivo" (claimed_at) y
        // "escribí algo en el item" (updated_at) queden como dos señales independientes — el reaper
        // exige que ambas estén frías antes de dar por muerto al worker.
        $this->renovarLease($sid);
    }

    /**
     * Renueva el lease del item reclamado por este worker. Best-effort: un fallo aquí no debe tumbar
     * el latido (la Torre seguiría mostrando la vuelta viva; a lo sumo el reaper la libera después).
     */
    public function renovarLease(string $sid): void
    {
        if (! $this->normalizaSid($sid)) {
            return;   // 'main'/'wt-exec' y demás sesiones sin slot no tienen lease que renovar
        }
        try {
            DB::table('roadmap_items')
                ->where('worker_sid', $sid)
                ->where('estado_aprobacion', 'en_progreso')
                ->update(['claimed_at' => now()]);
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    /** Marca el FIN de la vuelta de la sesión $sid (deja de reportar "corriendo"). */
    public function liveEnd(string $sid): void
    {
        $d = $this->readLive($sid);
        if (! $d) {
            return;
        }
        $d['finished']     = true;
        $d['heartbeat_at'] = time();
        if (! empty($d['log_path'])) {
            $d['log_tail'] = $this->tailFile($d['log_path']);
            $lines         = $this->readLogLines($d['log_path']);
            $d['fases']    = $this->parseFases($lines, (array) ($d['fases'] ?? []));
            // Captura el resumen de la vuelta (CIRCUITO_META) para dejarlo visible hasta la
            // siguiente vuelta (#349, punto "resumen al terminar").
            $meta = $this->parseMeta($lines);
            if ($meta !== null) {
                $meta['at'] = time();
                $d['meta']  = $meta;
            }
        }
        $this->writeLive($sid, $d);
    }

    /** ¿Hay ALGUNA sesión live corriendo (no terminada)? Barrera anti-solape del picker (#337). */
    public function anyRunning(): bool
    {
        foreach ($this->allLiveSessions() as $d) {
            if (! ($d['finished'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Estado derivado AGREGADO para la Torre (badge "corriendo" + overlap del picker). PURO-LECTURA
     * de BD (no toca archivos) → seguro para www-data. Con #334 puede haber varias sesiones a la
     * vez: `running` = alguna corre; los campos escalares reflejan la sesión "principal" = la
     * corriendo más reciente, o si ninguna corre la última que latió.
     */
    public function liveState(): array
    {
        $now      = time();
        $sessions = $this->allLiveSessions();

        if (! $sessions) {
            return ['running' => false, 'stale' => false, 'started_at' => null];
        }

        // Sesión principal: prioriza las corriendo; desempata por latido más reciente.
        uasort($sessions, function ($a, $b) {
            $ar = ! ($a['finished'] ?? false);
            $br = ! ($b['finished'] ?? false);
            if ($ar !== $br) {
                return $ar ? -1 : 1;
            }

            return (int) ($b['heartbeat_at'] ?? 0) <=> (int) ($a['heartbeat_at'] ?? 0);
        });
        $d = reset($sessions);

        $anyRunning = false;
        foreach ($sessions as $s) {
            if (! ($s['finished'] ?? false)) {
                $anyRunning = true;
                break;
            }
        }

        $finished  = (bool) ($d['finished'] ?? false);
        $hb        = (int) ($d['heartbeat_at'] ?? 0);
        $sinceBeat = max(0, $now - $hb);
        $running   = ! $finished;

        return [
            'running'               => $anyRunning,
            'finished'              => ! $anyRunning,
            'stale'                 => $running && $sinceBeat > self::HEARTBEAT_STALE_SEG,
            'sesiones_activas'      => count($sessions),
            'started_at'            => Carbon::createFromTimestamp((int) $d['started_at'])->toIso8601String(),
            'heartbeat_at'          => $hb ? Carbon::createFromTimestamp($hb)->toIso8601String() : null,
            'segundos_desde_latido' => $sinceBeat,
            'segundos_corriendo'    => $running ? max(0, $now - (int) $d['started_at']) : null,
            'current_item'          => isset($d['current_item']) ? ($d['current_item'] ?: null) : null,
        ];
    }

    /** Tail del log en vivo de la sesión principal (espejo en BD) para "Ver log en vivo". */
    public function liveLogTail(): string
    {
        $sessions = $this->allLiveSessions();
        if (! $sessions) {
            return '';
        }
        // Misma prioridad que liveState: corriendo primero, luego latido más reciente.
        uasort($sessions, function ($a, $b) {
            $ar = ! ($a['finished'] ?? false);
            $br = ! ($b['finished'] ?? false);
            if ($ar !== $br) {
                return $ar ? -1 : 1;
            }

            return (int) ($b['heartbeat_at'] ?? 0) <=> (int) ($a['heartbeat_at'] ?? 0);
        });

        return (string) (reset($sessions)['log_tail'] ?? '');
    }

    /**
     * Estructura para el visor "Trabajando ahora" (#349) y la rejilla de terminales (#350).
     * PURO-LECTURA de BD (no toca archivos) → seguro para www-data. Con #334 (worktrees paralelos)
     * `sesiones` trae UNA entrada por cada `circuito_live:<sid>` viva → los tabs/rejilla se
     * encienden solos. `resumen_ultima_vuelta` = CIRCUITO_META más reciente entre las sesiones.
     */
    public function trabajandoAhora(): array
    {
        // NOTA: no hay early-return con 0 sesiones — aunque el circuito esté ocioso, la rejilla
        // debe mostrar los N slots fijos como "esperando trabajo" (#334 B, relleno abajo).
        $sessions = $this->allLiveSessions();

        $now = time();

        // Títulos de TODOS los ids referenciados (item en curso + fases + resúmenes) en 1 query.
        $ids = [];
        foreach ($sessions as $d) {
            $ids[] = $d['current_item'] ?? null;
            foreach ((array) ($d['fases'] ?? []) as $f) {
                $ids[] = is_array($f) ? ($f['item_id'] ?? null) : null;
            }
            foreach ((array) (($d['meta'] ?? [])['items_tocados'] ?? []) as $mid) {
                $ids[] = $mid;
            }
        }
        $titulos = $this->titulosDe($ids);

        $sesiones = [];
        $resumen  = null;
        $resumenAt = -1;
        foreach ($sessions as $sid => $d) {
            $sesiones[] = $this->buildSesion($sid, $d, $now, $titulos);

            // El resumen mostrado = el CIRCUITO_META más reciente entre las sesiones.
            $meta = is_array($d['meta'] ?? null) ? $d['meta'] : null;
            if ($meta !== null && (int) ($meta['at'] ?? 0) >= $resumenAt) {
                $resumenAt = (int) ($meta['at'] ?? 0);
                $resumen   = [
                    'items_tocados' => array_map(
                        fn ($id) => ['id' => (int) $id, 'title' => $titulos[(int) $id] ?? null],
                        array_values((array) ($meta['items_tocados'] ?? []))
                    ),
                    'n_propuestas' => (int) ($meta['n_propuestas'] ?? 0),
                    'n_decisiones' => (int) ($meta['n_decisiones'] ?? 0),
                    'ejecuto'      => (bool) ($meta['ejecuto'] ?? false),
                    'resumen'      => (string) ($meta['resumen'] ?? ''),
                    'at'           => ! empty($meta['at']) ? Carbon::createFromTimestamp((int) $meta['at'])->toIso8601String() : null,
                ];
            }
        }

        // 6 TERMINALES PERSISTENTES (#334 B): el equipo tiene N slots fijos (wt-1..wt-N). Rellena los
        // que ahora mismo no tienen sesión live con un placeholder "esperando trabajo" (idle) → la
        // rejilla SIEMPRE muestra los N slots, no desaparecen al quedar ociosos.
        $n = $this->getParalelismo();
        $presentes = [];
        foreach ($sesiones as $s) {
            $presentes[$s['sid']] = true;
        }
        for ($k = 1; $k <= $n; $k++) {
            $sid = "wt-{$k}";
            if (empty($presentes[$sid])) {
                $sesiones[] = $this->buildSesionIdle($sid);
            }
        }

        // Orden estable = por número de slot (posición fija en la rejilla: wt-3 siempre en su celda).
        // Las sesiones legacy sin forma wt-K (p.ej. wt-exec) van al final.
        usort($sesiones, function ($a, $b) {
            $ka = $this->slotNum($a['sid']);
            $kb = $this->slotNum($b['sid']);
            if ($ka !== $kb) {
                return $ka <=> $kb;
            }

            return strcmp((string) $a['sid'], (string) $b['sid']);
        });

        // Nombre del roster por slot (wt-3 → "Tokyo"): la rejilla es un ROSTER del equipo (#334).
        $nombres = $this->nombresWorkers();
        foreach ($sesiones as &$s) {
            $s['nombre'] = $nombres[$s['sid']] ?? $s['sid'];
        }
        unset($s);

        return ['sesiones' => $sesiones, 'resumen_ultima_vuelta' => $resumen];
    }

    /** Nº de slot de un sid `wt-K` para ordenar (los no-wt-K se mandan al final). #334 B */
    private function slotNum(string $sid): int
    {
        return preg_match('/^wt-(\d+)$/', $sid, $m) ? (int) $m[1] : PHP_INT_MAX;
    }

    /** Placeholder de un slot OCIOSO del equipo — "esperando trabajo" (#334 B). */
    private function buildSesionIdle(string $sid): array
    {
        return [
            'sid'                   => $sid,
            'item'                  => null,
            'fase_actual'           => null,
            'pasos'                 => [],
            'log_tail'              => '',
            'artefactos'            => [],
            'running'               => false,
            'finished'              => false,
            'idle'                  => true,   // el frontend pinta "esperando trabajo"
            'stale'                 => false,
            'started_at'            => null,
            'heartbeat_at'          => null,
            'segundos_desde_latido' => null,
            'segundos_corriendo'    => null,
        ];
    }

    /** Construye el objeto-sesión (una terminal del visor #349/#350) a partir del blob live. */
    private function buildSesion(string $sid, array $d, int $now, array $titulos): array
    {
        $finished  = (bool) ($d['finished'] ?? false);
        $hb        = (int) ($d['heartbeat_at'] ?? 0);
        $sinceBeat = max(0, $now - $hb);
        $running   = ! $finished;

        $fases  = array_values(array_filter((array) ($d['fases'] ?? []), 'is_array'));
        $itemId = isset($d['current_item']) ? ($d['current_item'] ?: null) : null;

        $pasos = array_map(function ($f) use ($titulos) {
            $id = $f['item_id'] ?? null;

            return [
                'fase'    => $f['fase'] ?? null,
                'item_id' => $id,
                'title'   => $id ? ($titulos[$id] ?? null) : null,
                'at'      => ! empty($f['at']) ? Carbon::createFromTimestamp((int) $f['at'])->toIso8601String() : null,
            ];
        }, $fases);

        return [
            'sid'                   => $sid,
            'item'                  => $itemId ? ['id' => (int) $itemId, 'title' => $titulos[$itemId] ?? null] : null,
            'fase_actual'           => $fases ? ($fases[count($fases) - 1]['fase'] ?? null) : null,
            'pasos'                 => $pasos,
            'log_tail'              => (string) ($d['log_tail'] ?? ''),
            'artefactos'            => (array) ($d['artefactos'] ?? []),
            'running'               => $running,
            'finished'              => $finished,
            'idle'                  => false,
            'stale'                 => $running && $sinceBeat > self::HEARTBEAT_STALE_SEG,
            'started_at'            => Carbon::createFromTimestamp((int) $d['started_at'])->toIso8601String(),
            'heartbeat_at'          => $hb ? Carbon::createFromTimestamp($hb)->toIso8601String() : null,
            'segundos_desde_latido' => $sinceBeat,
            'segundos_corriendo'    => $running ? max(0, $now - (int) $d['started_at']) : null,
        ];
    }

    /** Mapa `id => title` para un set de ids de roadmap_items (una sola query). */
    private function titulosDe(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if (! $ids) {
            return [];
        }

        return RoadmapItem::whereIn('id', $ids)->pluck('title', 'id')->toArray();
    }

    /** #507 sub-paso 3 — ¿el circuito corre en modo CONTINUO (sin rondas)? Flag reversible. */
    public function esContinuo(): bool
    {
        return (bool) config('circuito.continuo', true);
    }

    /**
     * Próxima vuelta estimada a partir del intervalo del cron (config `circuito.interval_min`,
     * ESPEJO del crontab cada-30-min). No controla el cron real; solo informa a la Torre.
     *
     * #507 sub-paso 3 — En modo CONTINUO devuelve null: no hay "próxima vuelta" que anunciar porque
     * no hay rondas. Las terminales jalan trabajo en cuanto quedan libres, así que la pregunta útil
     * dejó de ser "¿cuándo es la próxima vuelta?" y pasó a ser "¿qué está corriendo ahora?" (el
     * panel de Terminales del sub-paso 4). Apagar `circuito.continuo` devuelve la estimación vieja.
     */
    public function proximaVueltaAt(): ?string
    {
        if ($this->esContinuo()) {
            return null;
        }

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

    /** Lee TODAS las líneas del log de la vuelta (archivos pequeños). Solo meganet lo puede leer. */
    private function readLogLines(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }
        $f = @file($path, FILE_IGNORE_NEW_LINES);

        return $f === false ? [] : $f;
    }

    /**
     * Parser DETERMINISTA de las migas `CIRCUITO_FASE: <fase> #<id>` (#349). Devuelve la
     * secuencia de fases en orden de aparición; a cada fase NUEVA (no vista en $prev) le sella
     * `at` = ahora (granularidad = intervalo del latido; honesto). El log solo crece, así que
     * el prefijo de $prev es estable → los timestamps ya sellados se conservan por índice.
     * Ignora tokens fuera del enum self::FASES.
     */
    private function parseFases(array $lines, array $prev): array
    {
        $seen = [];
        foreach ($lines as $ln) {
            if (! preg_match('/CIRCUITO_FASE:\s*([a-záéíóúñ]+)\s*(?:#(\d{1,6}))?/iu', $ln, $m)) {
                continue;
            }
            $fase = mb_strtolower($m[1]);
            if (! in_array($fase, self::FASES, true)) {
                continue;
            }
            $seen[] = ['fase' => $fase, 'item_id' => isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null];
        }

        $now = time();
        $out = [];
        foreach ($seen as $i => $ev) {
            $out[] = [
                'fase'    => $ev['fase'],
                'item_id' => $ev['item_id'],
                'at'      => (int) ($prev[$i]['at'] ?? $now),
            ];
        }

        return $out;
    }

    /** Último item_id de la secuencia de fases (para "tocando #NNN" determinista). */
    private function lastFaseItem(array $fases): ?int
    {
        for ($i = count($fases) - 1; $i >= 0; $i--) {
            if (! empty($fases[$i]['item_id'])) {
                return (int) $fases[$i]['item_id'];
            }
        }

        return null;
    }

    /**
     * Artefactos best-effort del log (#349): rama del circuito, commits y archivos mencionados.
     * INFORMATIVO — el log no siempre los expone; nunca es autoritativo.
     */
    private function parseArtefactos(array $lines): array
    {
        $text = implode("\n", $lines);

        preg_match_all('/circuito\/item-[\w.\/-]+/', $text, $mr);
        $ramas = array_values(array_unique($mr[0] ?? []));

        preg_match_all('/\bcommit[a-z]*\s+`?([0-9a-f]{7,40})`?/i', $text, $mc);
        $commits = array_values(array_unique($mc[1] ?? []));

        preg_match_all('/\b[\w][\w.\/-]*\.(?:blade\.php|php|vue|js|json|css|scss|sh)\b/', $text, $mf);
        $archivos = array_values(array_unique($mf[0] ?? []));

        return [
            'rama'     => $ramas ? $ramas[count($ramas) - 1] : null,   // la más reciente
            'commits'  => array_slice($commits, -6),
            'archivos' => array_slice($archivos, 0, 12),
        ];
    }

    /** Extrae el último bloque `CIRCUITO_META: {...}` del log como array (o null). (#349) */
    private function parseMeta(array $lines): ?array
    {
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (preg_match('/CIRCUITO_META:\s*(\{.*\})\s*$/', $lines[$i], $m)) {
                $j = json_decode($m[1], true);

                return is_array($j) ? $j : null;
            }
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

    // ─────────────────────────────────────────────────────────────────────────
    // COLA DE MERGE (#334 F0-fix) — la Torre (www-data) encola; el runner on-box
    // (meganet, en el checkout PRINCIPAL) ejecuta el merge real. Ver MergeRunner.
    // ─────────────────────────────────────────────────────────────────────────

    /** ¿El toggle de auto-merge está ON? (default auto-merge). */
    public function autoMergeOn(): bool
    {
        return $this->getModoIntegracion() === 'auto-merge';
    }

    /** Encola un merge (idempotente por item). trigger: 'boton' (Irving, autoridad) | 'auto'. */
    public function enqueueMerge(int $itemId, string $by, string $trigger = 'boton'): void
    {
        $cola = $this->mergeQueue();
        foreach ($cola as $e) {
            if ((int) ($e['item_id'] ?? 0) === $itemId) {
                return; // ya encolado
            }
        }
        $cola[] = ['item_id' => $itemId, 'by' => $by, 'trigger' => $trigger, 'at' => time()];
        $this->putSetting(self::MERGE_QUEUE_KEY, json_encode(array_values($cola), JSON_UNESCAPED_UNICODE));
        // Marca "en cola" en el resultado para feedback inmediato en la UI.
        $this->recordMergeResult($itemId, ['estado' => 'en_cola', 'by' => $by, 'trigger' => $trigger, 'at' => time()]);
    }

    /** Cola de merge pendiente (FIFO). */
    public function mergeQueue(): array
    {
        $raw = DB::table('settings')->where('key', self::MERGE_QUEUE_KEY)->value('value');
        $d = $raw ? json_decode((string) $raw, true) : [];

        return is_array($d) ? array_values(array_filter($d, 'is_array')) : [];
    }

    /** Saca el primer merge de la cola (FIFO) y persiste el resto. */
    public function dequeueMerge(): ?array
    {
        $cola = $this->mergeQueue();
        if (! $cola) {
            return null;
        }
        $first = array_shift($cola);
        $this->putSetting(self::MERGE_QUEUE_KEY, json_encode(array_values($cola), JSON_UNESCAPED_UNICODE));

        return $first;
    }

    /** ¿Este item está en la cola de merge? (para la UI). */
    public function isMergeQueued(int $itemId): bool
    {
        foreach ($this->mergeQueue() as $e) {
            if ((int) ($e['item_id'] ?? 0) === $itemId) {
                return true;
            }
        }

        return false;
    }

    /** Guarda el resultado del último intento de merge de un item (para la UI). */
    public function recordMergeResult(int $itemId, array $result): void
    {
        $this->putSetting(self::MERGE_RESULT_PREFIX . $itemId, json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    /** Resultado del último intento de merge de un item (o null). */
    public function mergeResult(int $itemId): ?array
    {
        $raw = DB::table('settings')->where('key', self::MERGE_RESULT_PREFIX . $itemId)->value('value');
        if (! $raw) {
            return null;
        }
        $d = json_decode((string) $raw, true);

        return is_array($d) ? $d : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PARALELISMO (#334 Fase 1) — N sesiones/worktrees a la vez.
    // ─────────────────────────────────────────────────────────────────────────

    public const PARALELISMO_KEY = 'circuito_paralelismo';

    /** N = cuántas sesiones a la vez (setting runtime; default de config). Clamp 1..12. */
    public function getParalelismo(): int
    {
        $v = DB::table('settings')->where('key', self::PARALELISMO_KEY)->value('value');
        $n = $v !== null ? (int) $v : (int) config('circuito.paralelismo', 6);

        return max(1, min(12, $n));
    }

    public function setParalelismo(int $n): void
    {
        $this->putSetting(self::PARALELISMO_KEY, (string) max(1, min(12, $n)));
    }

    /** Nombres de los workers (override runtime). Persisten y son renombrables por Irving. */
    public const WORKER_NOMBRES_KEY = 'circuito_worker_nombres';

    /**
     * Mapa `wt-K => Nombre` para los N slots. Default de config (circuito.worker_nombres), pisado
     * por los overrides de Irving en settings. Si faltan nombres para algún slot, cae a "wt-K".
     */
    public function nombresWorkers(): array
    {
        $defaults = (array) config('circuito.worker_nombres', []);
        $over = [];
        $raw = DB::table('settings')->where('key', self::WORKER_NOMBRES_KEY)->value('value');
        if ($raw !== null && is_array($d = json_decode((string) $raw, true))) {
            $over = $d;
        }

        $n = $this->getParalelismo();
        $map = [];
        for ($k = 1; $k <= $n; $k++) {
            $sid = "wt-{$k}";
            $nombre = trim((string) ($over[$sid] ?? ($defaults[$k - 1] ?? '')));
            $map[$sid] = $nombre !== '' ? $nombre : $sid;
        }

        return $map;
    }

    /** Nombre de un slot concreto (o el propio sid si no hay). */
    public function nombreWorker(?string $sid): ?string
    {
        $sid = trim((string) $sid);
        if ($sid === '') {
            return null;
        }

        return $this->nombresWorkers()[$sid] ?? $sid;
    }

    /** Renombra un worker (control de Irving). sid debe ser wt-K; nombre acotado. */
    public function setNombreWorker(string $sid, string $nombre): void
    {
        if (! preg_match('/^wt-\d+$/', $sid)) {
            return;
        }
        $raw = DB::table('settings')->where('key', self::WORKER_NOMBRES_KEY)->value('value');
        $map = ($raw !== null && is_array($d = json_decode((string) $raw, true))) ? $d : [];
        $nombre = mb_substr(trim($nombre), 0, 24);
        if ($nombre === '') {
            unset($map[$sid]);          // vaciar = volver al default
        } else {
            $map[$sid] = $nombre;
        }
        $this->putSetting(self::WORKER_NOMBRES_KEY, json_encode($map, JSON_UNESCAPED_UNICODE));
    }

    /** Segundos desde el último latido del scheduler (cron), o null si nunca latió. */
    public function schedulerBeatSecs(): ?int
    {
        $v = DB::table('settings')->where('key', 'circuito_scheduler_beat')->value('value');

        return $v !== null ? max(0, time() - (int) $v) : null;
    }

    /**
     * Reclama ATÓMICAMENTE el siguiente item elegible para un worker de pool continuo (#334 F1):
     * el primer ejecutable módulo-disjunto de lo que ya está en vuelo. Devuelve el id reclamado
     * (estado → en_progreso) o null si no hay trabajo / pausado. El llamador debe SERIALIZAR
     * (flock) para que dos workers no tomen items del mismo módulo a la vez.
     */
    public function claimNextParalelo(?string $workerSid = null): ?int
    {
        if ($this->isPaused()) {
            return null;
        }
        $items = $this->ejecutablesParalelo($this->modulosEnVuelo(), 1);
        if (! $items) {
            return null;
        }
        $id = (int) $items[0]['id'];
        // #507 sub-paso 3 — sella el LEASE al reclamar: a partir de aquí el worker debe renovarlo
        // con su latido (liveBeat) o el reaper libera el item.
        $update = ['estado_aprobacion' => 'en_progreso', 'claimed_at' => now(), 'updated_at' => now()];
        if ($sid = $this->normalizaSid($workerSid)) {
            $update['worker_sid'] = $sid;   // firma del worker (#334 A)
        }
        $claimed = DB::table('roadmap_items')
            ->where('id', $id)
            ->where(function ($q) {
                $q->whereIn('estado_aprobacion', ['aprobado_claude', 'aprobado_revisor', 'aprobado_irving'])
                    ->orWhere(fn ($x) => $x->where('nivel_riesgo', 'A')->where('estado_aprobacion', 'pendiente_revision'));
            })
            ->update($update);

        return $claimed === 1 ? $id : null;
    }

    /** Normaliza un id de worker a la forma `wt-K` (o null si no viene / inválido). #334 A */
    public function normalizaSid(?string $sid): ?string
    {
        $sid = trim((string) $sid);

        return preg_match('/^wt-\d+$/', $sid) ? $sid : null;
    }

    /**
     * Módulos NO-null de items EN VUELO (en_progreso — reclamados por una sesión o por un humano).
     * Se usan como pre-filtro conservador: no paralelizar dos items del mismo módulo (#334).
     */
    /** #432 B2 — sentinel de footprint SIN CLASIFICAR (lo pone el hook #427 al crear sin modulo). */
    public const MODULO_DESCONOCIDO = 'Sin clasificar';

    /** ¿El footprint es DESCONOCIDO? = null / vacío / el sentinel 'Sin clasificar'. */
    private function esFootprintDesconocido(?string $m): bool
    {
        $m = trim((string) $m);

        return $m === '' || strcasecmp($m, self::MODULO_DESCONOCIDO) === 0;
    }

    public function modulosEnVuelo(): array
    {
        return DB::table('roadmap_items')
            ->where('estado_aprobacion', 'en_progreso')
            ->whereNotNull('modulo')
            ->where('modulo', '!=', '')
            ->where('modulo', '!=', self::MODULO_DESCONOCIDO)   // #432 B2 — 'Sin clasificar' NO es módulo conocido
            ->pluck('modulo')
            ->unique()->values()->all();
    }

    /**
     * #432 B2 — ¿hay un item con footprint DESCONOCIDO (null/vacío/'Sin clasificar') en vuelo? Si lo
     * hay, no podemos garantizar que nada más se pise con él → nadie más se despacha hasta que integre.
     */
    public function desconocidoEnVuelo(): bool
    {
        return DB::table('roadmap_items')
            ->where('estado_aprobacion', 'en_progreso')
            ->where(fn ($q) => $q->whereNull('modulo')->orWhere('modulo', '')->orWhere('modulo', self::MODULO_DESCONOCIDO))
            ->exists();
    }

    /**
     * Items EJECUTABLES por el circuito en paralelo, módulo-disjuntos. Devuelve [{id, modulo}] hasta
     * $limit. Criterio: A `aprobado_claude` (auto) + (si revisor ON) `aprobado_revisor` (B autorizado),
     * excluyendo en_progreso/bloqueados (tomablePorCircuito, #341), urgentes primero (ordered, #337).
     * #432 B2 — REGLA ÚNICA de no-colisión (conservadora):
     *   - mismo `modulo` en vuelo o ya elegido esta ronda → SERIALIZA (se salta).
     *   - footprint DESCONOCIDO (`modulo` null/vacío) → corre SOLO: se despacha únicamente si nada hay
     *     en vuelo ni elegido esta ronda, y mientras corre no se despacha nada más (no sabemos qué
     *     archivos toca → no se puede garantizar disjunto). Poblar el footprint (B3) reduce esto.
     *   - [PARKED-PROD] (frontera dura de producción) queda FUERA del pool paralelo.
     */
    public function ejecutablesParalelo(array $excludeModulos, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }
        $revisor          = $this->revisorEnabled();
        $unknownEnVuelo   = $this->desconocidoEnVuelo();

        $rows = RoadmapItem::query()
            ->tomablePorCircuito()
            ->where('title', 'not like', '%[PARKED-PROD%')   // frontera dura de prod → nunca en paralelo
            ->where(function ($w) use ($revisor) {
                // A auto-aprobado por el circuito
                $w->where(function ($x) {
                    $x->where('nivel_riesgo', 'A')->where('estado_aprobacion', 'aprobado_claude');
                });
                // A sin triaje (pendiente_revision): A = seguro/aditivo por definición → auto-ejecutable.
                // El ejecutor por-item es la RED: si al leerlo resulta sensible, lo ESCALA (no lo ejecuta).
                // Sin esto, en el modelo pool-continuo los A pendientes nunca corrían (no había triaje).
                $w->orWhere(function ($x) {
                    $x->where('nivel_riesgo', 'A')->where('estado_aprobacion', 'pendiente_revision');
                });
                // Irving aprobó explícitamente (cualquier nivel) → ejecutable
                $w->orWhere('estado_aprobacion', 'aprobado_irving');
                // B autorizado por el revisor (solo si el flag está ON)
                if ($revisor) {
                    $w->orWhere('estado_aprobacion', 'aprobado_revisor');
                }
            })
            ->whereNotIn('status', ['done'])
            // #507 sub-paso 3 — orden de la COLA (urgente → por concluirse/reanudables → antigüedad),
            // no el de la bandeja: una terminal libre debe cerrar lo empezado antes de abrir nuevos.
            ->ordenCola()
            ->limit(200)
            ->get(['id', 'modulo']);

        $taken        = array_map('strval', $excludeModulos);
        $out          = [];
        $unknownTaken = false;
        foreach ($rows as $r) {
            // #432 B2 — 'Sin clasificar'/null/'' = footprint DESCONOCIDO → se normaliza a '' (corre solo).
            $mod = $this->esFootprintDesconocido($r->modulo) ? '' : trim((string) $r->modulo);

            if ($mod === '') {
                // Footprint desconocido → SOLO: nada en vuelo (excludeModulos → $taken), nada elegido
                // esta ronda, y ningún otro desconocido en vuelo/elegido. Si algo hay, espera su turno.
                if ($unknownEnVuelo || $unknownTaken || ! empty($taken) || ! empty($out)) {
                    continue;
                }
                $out[]        = ['id' => (int) $r->id, 'modulo' => ''];
                $unknownTaken = true;
                break; // corre solo: no se despacha nada más esta ronda
            }

            // Módulo conocido: serializa contra mismo módulo (en vuelo/elegido) y contra un desconocido
            // en vuelo/elegido (podría pisar cualquier archivo).
            if (in_array($mod, $taken, true) || $unknownEnVuelo || $unknownTaken) {
                continue;
            }
            $out[]   = ['id' => (int) $r->id, 'modulo' => $mod];
            $taken[] = $mod;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * #438 — colisión EN VUELO (footprint que no se conocía al despachar): dos items `en_progreso`
     * en distinto módulo/worktree cuyas ramas terminan tocando el(los) mismo(s) archivo(s). El
     * pre-filtro de `ejecutablesParalelo` (mismo módulo / desconocido) es conservador pero NO puede
     * ver esto — se detecta hasta que ambas ramas existen y tienen commits.
     *
     * Solo lee refs (git diff), NUNCA hace checkout/toca un worktree ajeno: las ramas son visibles
     * desde el checkout PRINCIPAL (comparten el mismo .git). Seguro de correr aunque otras vueltas
     * sigan corriendo en paralelo.
     */

    /** Archivos que cambia una rama vs su punto de partida en main (solo lectura, nunca falla fuerte). */
    public function footprintDeRama(string $branch): array
    {
        $base = $this->git(['merge-base', 'main', $branch]);
        if (! $base->isSuccessful()) {
            return [];
        }
        $sha = trim($base->getOutput());
        $diff = $this->git(['diff', '--name-only', $sha, $branch]);
        if (! $diff->isSuccessful()) {
            return [];
        }

        return array_values(array_filter(preg_split('/\R/', trim($diff->getOutput()))));
    }

    /**
     * Detecta colisiones nuevas entre items `en_progreso` con rama registrada y AÚN no pausados,
     * y PAUSA (marca `colision_pausada_por`) al perdedor: regla determinística = el que reclamó
     * más tarde (`updated_at` mayor; empate → mayor id). El ganador sigue corriendo normal.
     * NO toca `estado_aprobacion` (el perdedor se autopausa solo al llegar a `circuito:integrar`,
     * su propio checkpoint natural — nunca se mata el proceso en vuelo). Devuelve las colisiones
     * detectadas en esta pasada (para log/depuración).
     */
    public function detectarColisionesEnVuelo(): array
    {
        $rows = DB::table('roadmap_items')
            ->where('estado_aprobacion', 'en_progreso')
            ->whereNotNull('branch')
            ->where('branch', '!=', '')
            ->whereNull('colision_pausada_por')
            ->get(['id', 'branch', 'updated_at']);

        if ($rows->count() < 2) {
            return [];
        }

        $footprints = [];
        foreach ($rows as $r) {
            $footprints[$r->id] = $this->footprintDeRama($r->branch);
        }

        $detectadas = [];
        $porId = $rows->keyBy('id');
        foreach ($rows as $a) {
            foreach ($rows as $b) {
                if ($a->id >= $b->id) {
                    continue; // cada par una sola vez
                }
                $comunes = array_values(array_intersect($footprints[$a->id] ?? [], $footprints[$b->id] ?? []));
                if (! $comunes) {
                    continue;
                }

                // Perdedor = el que reclamó más tarde (updated_at mayor); empate → mayor id.
                $ta = Carbon::parse($a->updated_at)->timestamp;
                $tb = Carbon::parse($b->updated_at)->timestamp;
                if ($ta === $tb) {
                    $perdedor = $a->id > $b->id ? $porId[$a->id] : $porId[$b->id];
                    $ganador  = $a->id > $b->id ? $porId[$b->id] : $porId[$a->id];
                } else {
                    $perdedor = $ta > $tb ? $a : $b;
                    $ganador  = $ta > $tb ? $b : $a;
                }

                DB::table('roadmap_items')->where('id', $perdedor->id)->whereNull('colision_pausada_por')->update([
                    'colision_pausada_por' => $ganador->id,
                    'colision_pausada_at'  => now(),
                    'updated_at'           => now(),
                ]);
                $this->appendLog((int) $perdedor->id, 'colision-check', 'colision_pausada', [
                    'ganador' => $ganador->id, 'archivos' => array_slice($comunes, 0, 10),
                ]);

                $detectadas[] = ['ganador' => (int) $ganador->id, 'perdedor' => (int) $perdedor->id, 'archivos' => $comunes];
            }
        }

        return $detectadas;
    }

    /**
     * Reanuda items pausados por colisión cuyo ganador ya no bloquea: completado (integró), o ya
     * no está en vuelo (se canceló/escaló/rechazó — no dejar al perdedor colgado para siempre).
     * Limpia el flag + libera `worker_sid` + regresa `estado_aprobacion` al estado aprobado previo
     * (el circuito lo vuelve a despachar en una vuelta futura; `circuito:rama` rebasa su rama
     * existente sobre el main ya actualizado). Devuelve los ids reanudados.
     */
    public function reanudarColisionesResueltas(): array
    {
        $pausados = DB::table('roadmap_items')->whereNotNull('colision_pausada_por')->get(['id', 'colision_pausada_por', 'nivel_riesgo', 'log']);
        if ($pausados->isEmpty()) {
            return [];
        }

        $ganadorIds = $pausados->pluck('colision_pausada_por')->unique()->all();
        $ganadores = DB::table('roadmap_items')->whereIn('id', $ganadorIds)->get(['id', 'estado_aprobacion'])->keyBy('id');

        $reanudados = [];
        foreach ($pausados as $p) {
            $ganador = $ganadores->get($p->colision_pausada_por);
            $resuelto = ! $ganador || in_array($ganador->estado_aprobacion, ['completado', 'cancelado', 'rechazado'], true);
            if (! $resuelto) {
                continue;
            }

            $item = RoadmapItem::find($p->id);
            if (! $item) {
                continue;
            }
            $estadoPrevio = $this->estadoResumibleTrasColision($item);
            $item->colision_pausada_por = null;
            $item->colision_pausada_at  = null;
            $item->worker_sid           = null;
            $item->estado_aprobacion    = $estadoPrevio;
            $item->save();
            $this->appendLog((int) $item->id, 'colision-check', 'colision_reanudado', ['estado' => $estadoPrevio]);
            $reanudados[] = (int) $item->id;
        }

        return $reanudados;
    }

    /**
     * A qué `estado_aprobacion` regresar un item pausado por colisión para que el scheduler lo
     * vuelva a tomar sin re-triaje: el último `aprobado_*` de su propio log (respeta si fue Irving
     * quien lo aprobó); si no hay rastro, A auto-ejecutable → `aprobado_claude`; si no, a la
     * bandeja de Irving (fail-safe, nunca asumir que un B/C sin rastro es auto-ejecutable).
     */
    private function estadoResumibleTrasColision(RoadmapItem $item): string
    {
        $log = is_array($item->log) ? $item->log : [];
        foreach (array_reverse($log) as $entry) {
            $estado = $entry['estado'] ?? null;
            if (in_array($estado, ['aprobado_irving', 'aprobado_revisor', 'aprobado_claude'], true)) {
                return $estado;
            }
        }

        return $item->nivel_riesgo === 'A' ? 'aprobado_claude' : 'requiere_irving';
    }

    /** Log entry corta y uniforme para eventos del circuito sobre un item (best-effort, nunca tumba). */
    private function appendLog(int $itemId, string $por, string $evento, array $extra = []): void
    {
        try {
            $item = RoadmapItem::find($itemId);
            if (! $item) {
                return;
            }
            $log = is_array($item->log) ? $item->log : [];
            $log[] = ['ts' => now()->toIso8601String(), 'por' => $por, 'evento' => $evento] + $extra;
            $item->log = $log;
            $item->save();
        } catch (\Throwable $e) {
            // best-effort, nunca tumba al llamador
        }
    }

    private function git(array $args): \Symfony\Component\Process\Process
    {
        $p = new \Symfony\Component\Process\Process(array_merge(['git'], $args), base_path());
        $p->setTimeout(30);
        $p->run();

        return $p;
    }
}
