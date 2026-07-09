<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
    /** GET /api/roadmap-externo/{token} */
    public function index(Request $request, string $token): JsonResponse
    {
        if (! $this->tokenOk($token, (string) config('roadmap_externo.read_token'))) {
            return $this->deny($request, 'GET', $token);
        }

        $this->audit($request, 'GET', 'ok', ['items' => RoadmapItem::count()]);

        return response()->json([
            'generated_at'    => now()->toIso8601String(),
            'manual_criterios' => $this->manualCriterios(),
            'leyenda'         => [
                'nivel_riesgo'      => [
                    'A' => 'Seguro: aditivo, reversible, NO toca dinero/permisos/autenticación/producción. Ejecutable en automático si estado_aprobacion=aprobado_claude.',
                    'B' => 'Requiere confirmación de Irving en sesión.',
                    'C' => 'Decisión de diseño exclusiva de Irving. Jamás sin su decisión.',
                ],
                'estado_aprobacion' => RoadmapItem::ESTADOS_APROBACION,
            ],
            'items'           => RoadmapItem::ordered()->get()->map(fn ($i) => $this->serialize($i)),
        ]);
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
        $data = $request->validate([
            'estado_aprobacion'  => ['sometimes', 'string', 'in:' . implode(',', RoadmapItem::ESTADOS_APROBACION)],
            'nivel_riesgo'       => ['sometimes', 'nullable', 'string', 'in:' . implode(',', RoadmapItem::NIVELES_RIESGO)],
            'comentarios_claude' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        if (empty($data)) {
            return response()->json(['error' => 'Nada que actualizar. Campos permitidos: estado_aprobacion, nivel_riesgo, comentarios_claude'], 422);
        }

        $data['revisado_at']  = now();
        $data['aprobado_por'] = 'claude-cowork';

        $item->update($data);

        $this->audit($request, $verb, 'updated', ['id' => $id, 'campos' => array_keys($data)]);

        return response()->json(['ok' => true, 'item' => $this->serialize($item->fresh())]);
    }

    // ---------------------------------------------------------------------

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
