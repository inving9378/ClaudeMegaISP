<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * CIRCUITO DE MEJORA CONTINUA — conector MCP de la Hoja de Ruta (Streamable HTTP).
 *
 * Servidor MCP remoto (JSON-RPC 2.0 sobre HTTP POST) que expone la Hoja de Ruta como
 * "custom connector" de claude.ai para Claude Cowork — así Cowork lee/escribe el
 * roadmap SIN allowlist de red (el tráfico del conector va por los servidores de
 * Anthropic). Se aloja en dev detrás del HTTPS ya funcionando.
 *
 * Transporte: Streamable HTTP del spec MCP, variante STATELESS (una respuesta
 * `application/json` por request; sin SSE ni Mcp-Session-Id, ambos opcionales en el
 * spec). Un solo endpoint que responde POST; GET → 405.
 *
 * Auth: secreto en el path (claude.ai no permite fijar un bearer estático → mismo
 * patrón que roadmap-externo), comparado timing-safe. Rate limit + auditoría
 * (canal `mcp_roadmap`). Toda la lógica de negocio se reutiliza de
 * RoadmapCircuitoService (sin duplicar reglas ni guards).
 *
 * Métodos JSON-RPC: initialize · notifications/initialized · ping · tools/list ·
 * tools/call. Errores de protocolo → JSON-RPC error; errores de ejecución de una
 * tool → result con `isError: true` (convención MCP).
 */
class RoadmapMcpController extends Controller
{
    public function __construct(private RoadmapCircuitoService $svc)
    {
    }

    /**
     * POST /mcp/{secret} — endpoint MCP (Streamable HTTP).
     * El body es un único mensaje JSON-RPC (request o notification).
     */
    public function handle(Request $request, string $secret): JsonResponse
    {
        if (! $this->secretOk($secret)) {
            $this->audit($request, 'denied', ['secret_prefix' => substr($secret, 0, 6)]);
            // 401 SIN WWW-Authenticate: no queremos disparar el flujo OAuth de claude.ai;
            // el secreto correcto viaja en el path, así que un cliente legítimo nunca cae aquí.
            return response()->json([
                'jsonrpc' => '2.0',
                'error'   => ['code' => -32001, 'message' => 'No autorizado'],
                'id'      => null,
            ], 401);
        }

        $body = $request->json()->all();

        // Cuerpo inválido / no-JSON-RPC.
        if (! is_array($body) || empty($body)) {
            return $this->rpcError(null, -32700, 'Parse error: se esperaba un mensaje JSON-RPC.');
        }

        // El spec 2025-06-18 no usa batching; toleramos un array procesando el primero.
        if (array_is_list($body)) {
            $body = $body[0] ?? [];
        }

        $id     = $body['id'] ?? null;
        $method = $body['method'] ?? null;
        $params = $body['params'] ?? [];

        if (! is_string($method) || $method === '') {
            return $this->rpcError($id, -32600, 'Invalid Request: falta "method".');
        }

        // Notificaciones (sin `id`): el cliente no espera respuesta → 202 sin cuerpo.
        if (! array_key_exists('id', $body)) {
            $this->audit($request, 'notification', ['method' => $method]);
            return response()->json(null, 202);
        }

        $this->audit($request, 'request', ['method' => $method]);

        return match ($method) {
            'initialize'  => $this->rpcResult($id, $this->initializeResult($params)),
            'ping'        => $this->rpcResult($id, (object) []),
            'tools/list'  => $this->rpcResult($id, ['tools' => $this->toolDefinitions()]),
            'tools/call'  => $this->handleToolCall($id, $params),
            default       => $this->rpcError($id, -32601, "Method not found: {$method}"),
        };
    }

    /** GET/otros verbos → 405 (no ofrecemos stream SSE en este endpoint stateless). */
    public function methodNotAllowed(): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error'   => ['code' => -32600, 'message' => 'Use HTTP POST con un mensaje JSON-RPC.'],
            'id'      => null,
        ], 405);
    }

    // ── Handshake ────────────────────────────────────────────────────────

    private function initializeResult(array $params): array
    {
        // Eco de la versión del cliente si la manda; si no, la nuestra por defecto.
        $clientVersion = $params['protocolVersion'] ?? null;
        $version = is_string($clientVersion) && $clientVersion !== ''
            ? $clientVersion
            : (string) config('mcp_roadmap.protocol_version');

        return [
            'protocolVersion' => $version,
            'capabilities'    => ['tools' => (object) []],
            'serverInfo'      => [
                'name'    => (string) config('mcp_roadmap.server_name'),
                'version' => (string) config('mcp_roadmap.server_version'),
            ],
            'instructions' => 'Hoja de Ruta de MegaISP (Circuito de Mejora Continua). '
                . 'Usa roadmap_resumen para el panorama, roadmap_listar para explorar (filtros y '
                . 'paginación), roadmap_leer_item para el detalle y roadmap_escribir_item para fijar '
                . 'estado_aprobacion / nivel_riesgo / comentarios_claude. Guards: solo un item nivel A '
                . 'puede quedar aprobado_claude; nivel_riesgo solo se endurece (A→B→C); B y C topan en '
                . 'requiere_irving.',
        ];
    }

    // ── tools/list ───────────────────────────────────────────────────────

    private function toolDefinitions(): array
    {
        $estados = RoadmapItem::ESTADOS_APROBACION;
        $niveles = RoadmapItem::NIVELES_RIESGO;

        return [
            [
                'name'        => 'roadmap_resumen',
                'description' => 'Panorama global de la Hoja de Ruta: total de items y conteos por '
                    . 'estado_aprobacion y por nivel_riesgo. Úsalo para un vistazo rápido antes de listar.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => (object) [],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name'        => 'roadmap_listar',
                'description' => 'Lista items de la Hoja de Ruta (resumen + lista compacta paginada). '
                    . 'Filtros opcionales combinables por estado, nivel y módulo. Devuelve filas compactas '
                    . '(sin descripción/prompt); usa roadmap_leer_item para el detalle.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'estado'   => ['type' => 'string', 'enum' => $estados, 'description' => 'Filtra por estado_aprobacion.'],
                        'nivel'    => ['type' => 'string', 'enum' => $niveles, 'description' => 'Filtra por nivel_riesgo (A|B|C).'],
                        'modulo'   => ['type' => 'string', 'description' => 'Búsqueda parcial por módulo.'],
                        'page'     => ['type' => 'integer', 'minimum' => 1, 'description' => 'Página (def 1).'],
                        'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'Por página (def 50, máx 100).'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name'        => 'roadmap_leer_item',
                'description' => 'Detalle completo de un item por id, incluyendo description, prompt_para_claude, '
                    . 'comentarios_claude y log (bitácora).',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'ID del item.'],
                    ],
                    'required'   => ['id'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name'        => 'roadmap_escribir_item',
                'description' => 'Escritura ACOTADA sobre un item: solo estado_aprobacion, nivel_riesgo y '
                    . 'comentarios_claude. Guards server-side: solo un item nivel A puede quedar '
                    . 'aprobado_claude; nivel_riesgo solo se endurece (A→B→C), nunca se degrada; B y C '
                    . 'topan en requiere_irving.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id'                 => ['type' => 'integer', 'minimum' => 1, 'description' => 'ID del item a actualizar.'],
                        'estado_aprobacion'  => ['type' => 'string', 'enum' => $estados, 'description' => 'Nuevo estado_aprobacion.'],
                        'nivel_riesgo'       => ['type' => 'string', 'enum' => $niveles, 'description' => 'Nuevo nivel_riesgo (solo endurecer).'],
                        'comentarios_claude' => ['type' => 'string', 'description' => 'Comentario/reporte de Claude (máx 10000).'],
                    ],
                    'required'   => ['id'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    // ── tools/call ───────────────────────────────────────────────────────

    private function handleToolCall(mixed $id, array $params): JsonResponse
    {
        $name = $params['name'] ?? null;
        $args = $params['arguments'] ?? [];
        if (! is_array($args)) {
            $args = [];
        }

        if (! is_string($name) || $name === '') {
            return $this->rpcError($id, -32602, 'Invalid params: falta "name" de la tool.');
        }

        return match ($name) {
            'roadmap_resumen'       => $this->toolResumen($id),
            'roadmap_listar'        => $this->toolListar($id, $args),
            'roadmap_leer_item'     => $this->toolLeerItem($id, $args),
            'roadmap_escribir_item' => $this->toolEscribirItem($id, $args),
            default                 => $this->rpcError($id, -32602, "Tool desconocida: {$name}"),
        };
    }

    private function toolResumen(mixed $id): JsonResponse
    {
        return $this->toolOk($id, [
            'generated_at' => now()->toIso8601String(),
            'resumen'      => $this->svc->resumen(),
        ]);
    }

    private function toolListar(mixed $id, array $args): JsonResponse
    {
        $v = $this->validateArgs($args, [
            'estado'   => ['sometimes', 'string', 'in:' . implode(',', RoadmapItem::ESTADOS_APROBACION)],
            'nivel'    => ['sometimes', 'string', 'in:' . implode(',', RoadmapItem::NIVELES_RIESGO)],
            'modulo'   => ['sometimes', 'string', 'max:100'],
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if (is_string($v)) {
            return $this->toolError($id, $v);
        }

        $page    = (int) ($v['page'] ?? 1);
        $perPage = (int) ($v['per_page'] ?? 50);

        $payload = array_merge(
            ['resumen' => $this->svc->resumen()],
            $this->svc->listar($v['estado'] ?? null, $v['nivel'] ?? null, $v['modulo'] ?? null, $page, $perPage)
        );

        return $this->toolOk($id, $payload);
    }

    private function toolLeerItem(mixed $id, array $args): JsonResponse
    {
        $v = $this->validateArgs($args, [
            'id' => ['required', 'integer', 'min:1'],
        ]);
        if (is_string($v)) {
            return $this->toolError($id, $v);
        }

        $item = $this->svc->find((int) $v['id']);
        if (! $item) {
            return $this->toolError($id, "Item {$v['id']} no encontrado.");
        }

        return $this->toolOk($id, ['item' => $this->svc->serialize($item)]);
    }

    private function toolEscribirItem(mixed $id, array $args): JsonResponse
    {
        $v = $this->validateArgs($args, array_merge(
            ['id' => ['required', 'integer', 'min:1']],
            $this->svc->writeFieldRules()
        ));
        if (is_string($v)) {
            return $this->toolError($id, $v);
        }

        $item = $this->svc->find((int) $v['id']);
        if (! $item) {
            return $this->toolError($id, "Item {$v['id']} no encontrado.");
        }

        $data = array_intersect_key($v, array_flip(['estado_aprobacion', 'nivel_riesgo', 'comentarios_claude']));
        if (empty($data)) {
            return $this->toolError($id, 'Nada que actualizar. Campos permitidos: estado_aprobacion, nivel_riesgo, comentarios_claude.');
        }

        if ($motivo = $this->svc->guard($item, $data)) {
            $this->audit(request(), 'rejected_guard', ['id' => (int) $v['id'], 'motivo' => $motivo]);
            return $this->toolError($id, $motivo);
        }

        $fresh = $this->svc->applyWrite($item, $data, 'claude-mcp');
        $this->audit(request(), 'updated', ['id' => (int) $v['id'], 'campos' => array_keys($data)]);

        return $this->toolOk($id, ['ok' => true, 'item' => $this->svc->serialize($fresh)]);
    }

    // ── Helpers de validación y respuesta ────────────────────────────────

    /** Valida los argumentos de una tool. Devuelve el array validado o un string con el error. */
    private function validateArgs(array $args, array $rules): array|string
    {
        $validator = Validator::make($args, $rules);
        if ($validator->fails()) {
            return 'Parámetros inválidos: ' . $validator->errors()->first();
        }
        return $validator->validated();
    }

    /** Resultado de tool exitoso: content de tipo text con el JSON del payload. */
    private function toolOk(mixed $id, array $payload): JsonResponse
    {
        return $this->rpcResult($id, [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            ]],
            'isError' => false,
        ]);
    }

    /** Error de EJECUCIÓN de una tool (convención MCP: result con isError=true, no error de protocolo). */
    private function toolError(mixed $id, string $message): JsonResponse
    {
        return $this->rpcResult($id, [
            'content' => [['type' => 'text', 'text' => $message]],
            'isError' => true,
        ]);
    }

    private function rpcResult(mixed $id, mixed $result): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private function rpcError(mixed $id, int $code, string $message): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]]);
    }

    private function secretOk(string $given): bool
    {
        $expected = (string) config('mcp_roadmap.secret');
        // Un secreto no configurado NUNCA valida (evita bypass con string vacío).
        return $expected !== '' && hash_equals($expected, $given);
    }

    private function audit(Request $request, string $result, array $extra = []): void
    {
        Log::channel('mcp_roadmap')->info('acceso-mcp', array_merge([
            'result' => $result,
            'ip'     => $request->ip(),
            'ua'     => substr((string) $request->userAgent(), 0, 200),
        ], $extra));
    }
}
