<?php

namespace App\Http\Controllers\IA;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Modules\Core\ModuleManager\Services\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Chat IA flotante — Fase 1 de #9.
 *
 * Inyecta el contexto dinámico de módulos (ModuleRegistry::getAiContext():
 * knowledge + actions declaradas en cada module.json) al system prompt, para
 * que el asistente conozca qué módulos/acciones existen REALMENTE.
 *
 * READ-ONLY: en esta fase el chat informa/orienta; NO ejecuta acciones ni
 * cambia datos (la ejecución vía tool-calling es la Fase 2).
 *
 * La API key y el modelo salen de config/Hub/.env (config/services.php +
 * Integration Hub vía ClaudeApiClient), nunca hardcodeados.
 */
class IAChatController extends Controller
{
    public function __construct(
        private ModuleRegistry $registry,
        private ClaudeApiClient $claude,
    ) {
    }

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message'           => 'required|string|max:4000',
            'history'           => 'sometimes|array',
            'history.*.role'    => 'sometimes|in:user,assistant',
            'history.*.content' => 'sometimes|string',
            'context'           => 'nullable|string|max:255',
        ]);

        // Historial -> formato Claude (filtra vacíos, normaliza rol).
        $messages = [];
        foreach ($data['history'] ?? [] as $turn) {
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $messages[] = [
                'role'    => ($turn['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $data['message']];

        try {
            $response = $this->claude->messages([
                // Modelo desde config (.env CLAUDE_MODEL); fallback a un modelo válido
                // verificado en este entorno. `?:` para tolerar config vacía.
                'model'      => config('services.claude.model') ?: 'claude-sonnet-4-6',
                'max_tokens' => 1024,
                'system'     => $this->buildSystemPrompt($data['context'] ?? null),
                'messages'   => $messages,
            ]);

            return response()->json([
                'success'       => true,
                'message'       => $this->extractText($response['content'] ?? []),
                'action_type'   => null,   // Fase 2: ejecución de acciones
                'action_result' => null,
            ]);
        } catch (\Throwable $e) {
            // Error VISIBLE: se loguea en servidor y se devuelve un mensaje claro
            // (no se traga el fallo; el frontend lo muestra inline).
            Log::channel('claude')->error('[IAChat] fallo al responder: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'   => 'No se pudo obtener respuesta del asistente. Inténtalo de nuevo en un momento.',
            ]);
        }
    }

    /**
     * Construye el system prompt con el contexto dinámico de los módulos activos.
     */
    private function buildSystemPrompt(?string $context): string
    {
        $lines = [];
        $lines[] = 'Eres el asistente de IA de MegaISP, un sistema de gestión para proveedores de internet (ISP). Respondes SIEMPRE en español, de forma breve y concreta.';
        $lines[] = 'Conoces los módulos que están ACTUALMENTE registrados en el sistema y las acciones que cada uno declara. Basa tus respuestas en ese contexto real; si algo no está en la lista, dilo en vez de inventarlo.';
        $lines[] = 'IMPORTANTE (esta versión): solo INFORMAS y ORIENTAS. NO ejecutas acciones ni modificas datos. Si te piden ejecutar algo, explica dónde y cómo hacerlo en la interfaz, y aclara que la ejecución automática todavía no está disponible.';

        if (!empty($context)) {
            $lines[] = "Contexto: el usuario está navegando en la ruta \"{$context}\".";
        }

        $lines[] = '';
        $lines[] = '=== MÓDULOS Y ACCIONES REGISTRADOS ===';

        $aiContext = $this->registry->getAiContext();
        foreach ($aiContext as $mod) {
            $slug = $mod['_module'] ?? 'desconocido';
            $lines[] = "## Módulo: {$slug}";

            if (!empty($mod['knowledge'])) {
                $lines[] = trim((string) $mod['knowledge']);
            }
            foreach (($mod['actions'] ?? []) as $action) {
                $desc = $action['description'] ?? '';
                $ep   = $action['endpoint'] ?? '';
                if ($desc !== '' || $ep !== '') {
                    $lines[] = "- {$desc}" . ($ep !== '' ? " ({$ep})" : '');
                }
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /** Extrae el texto de los bloques de respuesta de Claude. */
    private function extractText(array $content): string
    {
        $text = '';
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        return trim($text) !== '' ? trim($text) : 'No tengo una respuesta para eso en este momento.';
    }
}
