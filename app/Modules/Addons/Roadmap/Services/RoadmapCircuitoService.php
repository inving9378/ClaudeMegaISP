<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
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

    public function setPaused(bool $paused): void
    {
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
}
