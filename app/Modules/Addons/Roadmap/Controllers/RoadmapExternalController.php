<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * CIRCUITO DE MEJORA CONTINUA — acceso externo sin login (Parte 1.2)
 *
 * Claude Cowork corre FUERA de esta red y revisa la Hoja de Ruta desde internet.
 * La hoja contiene debilidades del sistema → JAMÁS abierta sin token.
 *
 * - GET  /api/roadmap-externo/{token}                → lectura completa (manual + items)
 * - POST /api/roadmap-externo/{token}/item/{id}      → escritura ACOTADA (3 campos)
 *
 * Sin sesión, sin cookies (rutas fuera del grupo `web`). Token en el path,
 * comparado con hash_equals (timing-safe). Cada acceso se audita en el canal
 * `roadmap_externo`. Rutas fuera de menús y del sitemap.
 */
class RoadmapExternalController extends Controller
{
    /**
     * GET /api/roadmap-externo/{token}
     *
     * El fetcher de Cowork trunca respuestas largas y el manual (134 KB) tapaba los
     * items. Por eso la respuesta se modela por MODOS (mismo token, mismos guards):
     *  - ?id=N          → detalle COMPLETO de un item.
     *  - ?solo=manual   → solo el manual + leyenda (la respuesta pesada, bajo demanda).
     *  - ?solo=items    → solo la lista compacta (filtrada/paginada), sin resumen ni manual.
     *  - (default)      → resumen (conteos) + leyenda + lista compacta paginada. SIN manual.
     * Filtros combinables: ?estado= ?nivel= ?modulo=  · Paginación: ?page= ?per_page=.
     */
    public function index(Request $request, string $token): JsonResponse
    {
        if (! $this->tokenOk($token, (string) config('roadmap_externo.read_token'))) {
            return $this->deny($request, 'GET', $token);
        }

        // Whitelist + enums de TODOS los parámetros (nada de SQL crudo con input externo).
        $p = $this->validateExternal($request, [
            'solo'     => ['sometimes', 'string', 'in:manual,items'],
            'id'       => ['sometimes', 'integer', 'min:1'],
            'estado'   => ['sometimes', 'string', 'in:' . implode(',', RoadmapItem::ESTADOS_APROBACION)],
            'nivel'    => ['sometimes', 'string', 'in:' . implode(',', RoadmapItem::NIVELES_RIESGO)],
            'modulo'   => ['sometimes', 'string', 'max:100'],
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        // ── Detalle completo de UN item (?id=N) ──
        if (isset($p['id'])) {
            return $this->respondItem($request, 'GET', (int) $p['id']);
        }

        // ── Solo el manual (respuesta pesada, bajo demanda) ──
        if (($p['solo'] ?? null) === 'manual') {
            $this->audit($request, 'GET', 'ok', ['modo' => 'manual']);
            return response()->json([
                'generated_at'     => now()->toIso8601String(),
                'manual_criterios' => $this->manualCriterios(),
                'leyenda'          => $this->leyenda(),
            ]);
        }

        // ── Lista compacta (default y ?solo=items): filtros + paginación ──
        $page    = (int) ($p['page'] ?? 1);
        $perPage = (int) ($p['per_page'] ?? 50);
        $payload = $this->buildList($p['estado'] ?? null, $p['nivel'] ?? null, $p['modulo'] ?? null, $page, $perPage, ($p['solo'] ?? null) !== 'items');

        $this->audit($request, 'GET', 'ok', ['modo' => $p['solo'] ?? 'default', 'filtros' => $payload['filtros_aplicados'], 'page' => $page]);

        return response()->json($payload);
    }

    /**
     * GET /api/roadmap-externo/{token}/item/{id}  — DETALLE por PATH (lectura).
     * El fetcher de Cowork descarta el query string, así que ?id=N no le sirve; esta
     * variante path-based es a prueba de cualquier fetcher. Mismo token de lectura.
     */
    public function showItem(Request $request, string $token, int $id): JsonResponse
    {
        if (! $this->tokenOk($token, (string) config('roadmap_externo.read_token'))) {
            return $this->deny($request, 'GET-ITEM', $token);
        }
        return $this->respondItem($request, 'GET-ITEM', $id);
    }

    /**
     * GET /api/roadmap-externo/{token}/q/{estado}/{nivel}/{page}/{perpage}
     * Lista FILTRADA por PATH (a prueba de fetchers que descartan el query string).
     * Cualquier segmento acepta "-" como comodín. Ej: /q/pendiente_revision/A/1/50,
     * /q/-/B/2/50. Mismas validaciones whitelist/enums; segmento inválido → 422.
     */
    public function queryPath(Request $request, string $token, string $estado, string $nivel, string $page, string $perpage): JsonResponse
    {
        if (! $this->tokenOk($token, (string) config('roadmap_externo.read_token'))) {
            return $this->deny($request, 'GET-Q', $token);
        }

        // "-" = comodín (sin filtro).
        $estado = $estado === '-' ? null : $estado;
        $nivel  = $nivel === '-' ? null : $nivel;

        // Mismas whitelist/enums que el query string (nada de SQL crudo), vía Request sintético.
        $v = $this->validateExternal(new Request([
            'estado' => $estado, 'nivel' => $nivel, 'page' => $page, 'perpage' => $perpage,
        ]), [
            'estado'  => ['nullable', 'string', 'in:' . implode(',', RoadmapItem::ESTADOS_APROBACION)],
            'nivel'   => ['nullable', 'string', 'in:' . implode(',', RoadmapItem::NIVELES_RIESGO)],
            'page'    => ['required', 'integer', 'min:1'],
            'perpage' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $payload = $this->buildList($estado, $nivel, null, (int) $v['page'], (int) $v['perpage'], true);

        $this->audit($request, 'GET-Q', 'ok', ['filtros' => $payload['filtros_aplicados'], 'page' => (int) $v['page']]);

        return response()->json($payload);
    }

    /** Respuesta de detalle de un item (compartida por ?id=N y el path /item/{id}). */
    private function respondItem(Request $request, string $verb, int $id): JsonResponse
    {
        $item = RoadmapItem::find($id);
        if (! $item) {
            $this->audit($request, $verb, 'not_found', ['id' => $id]);
            return response()->json(['error' => 'Item no encontrado'], 404);
        }
        $this->audit($request, $verb, 'ok', ['modo' => 'detalle', 'id' => $id]);
        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'item'         => $this->serialize($item),
        ]);
    }

    /** Construye la lista compacta (filtros parametrizados + paginación). Compartida por index() y queryPath(). */
    private function buildList(?string $estado, ?string $nivel, ?string $modulo, int $page, int $perPage, bool $conResumen): array
    {
        $q = RoadmapItem::query();
        if ($estado) $q->where('estado_aprobacion', $estado);
        if ($nivel)  $q->where('nivel_riesgo', $nivel);
        if ($modulo !== null && $modulo !== '') $q->where('modulo', 'like', '%' . $modulo . '%');

        $total = (clone $q)->count();
        $items = $q->ordered()->forPage($page, $perPage)->get()->map(fn ($i) => $this->compactItem($i));

        $filtros = array_filter(
            ['estado' => $estado, 'nivel' => $nivel, 'modulo' => $modulo],
            fn ($val) => $val !== null && $val !== ''
        );

        $payload = [
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

        if ($conResumen) {
            $payload = array_merge([
                'generated_at' => now()->toIso8601String(),
                'resumen'      => $this->resumen(),
                'leyenda'      => $this->leyenda(),
                '_ayuda'       => $this->ayuda(),
            ], $payload);
        }

        return $payload;
    }

    /** Panorama global (todos los items, sin filtrar) para el modo resumen. */
    private function resumen(): array
    {
        return [
            'total'      => RoadmapItem::count(),
            'por_estado' => RoadmapItem::selectRaw('estado_aprobacion, count(*) as n')
                ->groupBy('estado_aprobacion')->pluck('n', 'estado_aprobacion'),
            'por_nivel'  => RoadmapItem::selectRaw("COALESCE(nivel_riesgo,'sin_clasificar') as nivel, count(*) as n")
                ->groupBy('nivel')->pluck('n', 'nivel'),
        ];
    }

    /** Fila compacta (sin description/prompt) — para listas. El detalle va por ?id=N. */
    private function compactItem(RoadmapItem $i): array
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

    private function leyenda(): array
    {
        return [
            'nivel_riesgo' => [
                'A' => 'Seguro: aditivo, reversible, NO toca dinero/permisos/autenticación/producción. Ejecutable en automático si estado_aprobacion=aprobado_claude.',
                'B' => 'Requiere confirmación de Irving en sesión.',
                'C' => 'Decisión de diseño exclusiva de Irving. Jamás sin su decisión.',
            ],
            'estado_aprobacion'  => RoadmapItem::ESTADOS_APROBACION,
            'aprobacion_externa' => 'Por esta vía SOLO un item nivel A puede quedar aprobado_claude; B y C topan en requiere_irving. El nivel_riesgo solo se puede endurecer (A→B→C), nunca degradar.',
        ];
    }

    private function ayuda(): array
    {
        return [
            'parametros' => [
                'solo'     => 'manual | items',
                'id'       => 'detalle completo de un item (N)',
                'estado'   => implode('|', RoadmapItem::ESTADOS_APROBACION),
                'nivel'    => implode('|', RoadmapItem::NIVELES_RIESGO),
                'modulo'   => 'texto (búsqueda parcial)',
                'page'     => 'nº de página (def 1)',
                'per_page' => 'por página (def 50, máx 100)',
            ],
            'ejemplos' => [
                'resumen + primera tanda' => '(sin parámetros)',
                'pendientes nivel A'      => '?estado=pendiente_revision&nivel=A',
                'segunda tanda de items'  => '?solo=items&page=2&per_page=50',
                'detalle de un item'      => '?id=211',
                'solo el manual'          => '?solo=manual',
            ],
            // Si tu fetcher DESCARTA el query string (caso Cowork), usa la variante PATH:
            'variante_path' => [
                'lista_filtrada' => '/{token}/q/{estado}/{nivel}/{page}/{perpage}   ("-" = comodín)',
                'ej_pendientes_A'=> '/{token}/q/pendiente_revision/A/1/50',
                'ej_nivel_B_pag2'=> '/{token}/q/-/B/2/50',
                'detalle'        => '/{token}/item/{id}',
            ],
        ];
    }

    /** POST /api/roadmap-externo/{token}/item/{id} */
    public function updateItem(Request $request, string $token, int $id): JsonResponse
    {
        if (! $this->tokenOk($token, (string) config('roadmap_externo.write_token'))) {
            return $this->deny($request, 'POST', $token);
        }

        return $this->writeItem($request, 'POST', $id);
    }

    /**
     * GET /api/roadmap-externo/{token}/item/{id}/set?estado_aprobacion=..&nivel_riesgo=..&comentarios_claude=..
     * Variante de escritura por GET para el fetcher de Cowork (solo hace GET).
     * Idéntica al POST: mismo token de escritura, misma allowlist, mismos guards y log.
     */
    public function setItem(Request $request, string $token, int $id): JsonResponse
    {
        if (! $this->tokenOk($token, (string) config('roadmap_externo.write_token'))) {
            return $this->deny($request, 'GET-SET', $token);
        }

        return $this->writeItem($request, 'GET-SET', $id);
    }

    /**
     * GET /api/roadmap-externo/{token}/item/{id}/set/{estado}/{nivel}/{comentario?}
     * ESCRITURA por PATH — el fetcher de Cowork descarta el query string (rechaza incluso
     * la petición con ?params), así que los campos van en el path. "-" = comodín (no cambiar).
     * `comentario` es el último segmento OPCIONAL, URL-encoded (%20 = espacio), máx 1000 chars.
     * Traduce los segmentos a los MISMOS 3 campos y delega en writeItem() → hereda whitelist/
     * enums, guards (solo A→aprobado_claude, no degradar nivel), 422 y auditoría.
     */
    public function setItemPath(Request $request, string $token, int $id, string $estado, string $nivel, ?string $comentario = null): JsonResponse
    {
        if (! $this->tokenOk($token, (string) config('roadmap_externo.write_token'))) {
            return $this->deny($request, 'GET-SETPATH', $token);
        }

        $fields = [];
        if ($estado !== '-') $fields['estado_aprobacion'] = $estado;
        if ($nivel  !== '-') $fields['nivel_riesgo']      = $nivel;

        if ($comentario !== null && $comentario !== '') {
            $decoded = urldecode($comentario);
            if (mb_strlen($decoded) > 1000) {
                return response()->json(['error' => 'comentario excede 1000 caracteres'], 422);
            }
            $fields['comentarios_claude'] = $decoded;
        }

        // Mismo camino que el /set por query: validación de enums + guards + update + log.
        return $this->writeItem(new Request($fields), 'GET-SETPATH', $id);
    }

    /**
     * Cuerpo ÚNICO de la escritura acotada — compartido por el POST y el GET/set,
     * para que ambas vías tengan EXACTAMENTE la misma allowlist, validaciones,
     * guards de seguridad y auditoría. `$request->validate` lee tanto body (POST)
     * como query params (GET), así que sirve igual para las dos.
     */
    private function writeItem(Request $request, string $verb, int $id): JsonResponse
    {
        $item = RoadmapItem::find($id);
        if (! $item) {
            $this->audit($request, $verb, 'not_found', ['id' => $id]);
            return response()->json(['error' => 'Item no encontrado'], 404);
        }

        // SOLO estos 3 campos son escribibles por esta vía. Nada más.
        $data = $this->validateExternal($request, [
            'estado_aprobacion'  => ['sometimes', 'string', 'in:' . implode(',', RoadmapItem::ESTADOS_APROBACION)],
            'nivel_riesgo'       => ['sometimes', 'nullable', 'string', 'in:' . implode(',', RoadmapItem::NIVELES_RIESGO)],
            'comentarios_claude' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        if (empty($data)) {
            return response()->json(['error' => 'Nada que actualizar. Campos permitidos: estado_aprobacion, nivel_riesgo, comentarios_claude'], 422);
        }

        // GUARDS DE SEGURIDAD (server-side, aplican a POST y GET por igual).
        if ($motivo = $this->guardExternalWrite($item, $data)) {
            $this->audit($request, $verb, 'rejected_guard', ['id' => $id, 'motivo' => $motivo]);
            return response()->json(['error' => $motivo], 422);
        }

        $data['revisado_at']  = now();
        $data['aprobado_por'] = 'claude-cowork';

        $item->update($data);

        $this->audit($request, $verb, 'updated', ['id' => $id, 'campos' => array_keys($data)]);

        return response()->json(['ok' => true, 'item' => $this->serialize($item->fresh())]);
    }

    /**
     * Candados que la vía externa NO puede saltar (hallazgos del auditor del circuito).
     * Devuelve el motivo del rechazo (string) o null si pasa.
     *
     *  a) NO degradar nivel_riesgo hacia menos restrictivo (A<B<C): solo endurecer.
     *     C→B, C→A y B→A quedan prohibidos; A→B, A→C, B→C o igual, permitidos.
     *     Quitar la clasificación (X→null) también es degradar → prohibido.
     *  b) SOLO un item nivel A puede quedar 'aprobado_claude' (= validado y listo para
     *     ejecución automática) por esta vía. Para B y C —y sin clasificar— el máximo es
     *     'requiere_irving' (Claude validó el plan; la confirmación final la da Irving en
     *     sesión, NO por la vía externa). Se evalúa contra el nivel EFECTIVO tras el update
     *     (bloquea subir el nivel y auto-aprobar en la misma llamada).
     */
    private function guardExternalWrite(RoadmapItem $item, array $data): ?string
    {
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

        // (b) Solo un item nivel A puede quedar aprobado_claude (auto-ejecutable) por esta
        //     vía. B, C y sin clasificar topan en requiere_irving.
        if (($data['estado_aprobacion'] ?? null) === 'aprobado_claude' && $nivelEfectivo !== 'A') {
            $n = $nivelEfectivo ?? 'sin clasificar';
            return "Solo un item nivel A puede quedar 'aprobado_claude' por la vía externa "
                . "(este es nivel {$n}); para B/C el máximo es 'requiere_irving'.";
        }

        return null;
    }

    // ---------------------------------------------------------------------

    /**
     * Valida con whitelist/enums SIEMPRE devolviendo 422 JSON si falla. Estas rutas
     * viven FUERA del grupo `web`, así que `$request->validate()` redirigiría (302) a
     * un curl/fetcher que no manda `Accept: application/json`. Aquí se fuerza JSON.
     */
    private function validateExternal(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new HttpResponseException(
                response()->json(['error' => 'Parámetros inválidos', 'detalles' => $validator->errors()], 422)
            );
        }
        return $validator->validated();
    }

    private function tokenOk(string $given, string $expected): bool
    {
        // Un token no configurado NUNCA valida (evita bypass con string vacío).
        return $expected !== '' && hash_equals($expected, $given);
    }

    private function deny(Request $request, string $verb, string $token): JsonResponse
    {
        $this->audit($request, $verb, 'denied', ['token_prefix' => substr($token, 0, 6)]);
        return response()->json(['error' => 'No autorizado'], 403);
    }

    private function audit(Request $request, string $verb, string $result, array $extra = []): void
    {
        Log::channel('roadmap_externo')->info('acceso-externo', array_merge([
            'verb'   => $verb,
            'result' => $result,
            'ip'     => $request->ip(),
            'ua'     => substr((string) $request->userAgent(), 0, 200),
        ], $extra));
    }

    private function serialize(RoadmapItem $i): array
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

    private function manualCriterios(): string
    {
        // Manual destilado (reglas del circuito + convenciones + negocio) …
        $destilado = base_path('docs/manual-criterios-circuito.md');
        $partes = [];
        $partes[] = is_file($destilado)
            ? file_get_contents($destilado)
            : "Manual destilado no disponible (docs/manual-criterios-circuito.md ausente).";

        // … + el CLAUDE.md COMPLETO y VIVO (nunca desincronizado, se lee del repo).
        $claudeMd = base_path('CLAUDE.md');
        if (is_file($claudeMd)) {
            $partes[] = "\n\n===================================================================\n"
                . "# ANEXO — CLAUDE.md COMPLETO (fuente viva del repositorio)\n"
                . "===================================================================\n\n"
                . file_get_contents($claudeMd);
        }

        return implode("\n", $partes);
    }
}
