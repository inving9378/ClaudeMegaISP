<?php

namespace App\Http\Controllers\IA;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
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
 * La IA (integración + modelo) se decide en Integraciones → Módulos IA
 * (clave asistente.chat_flotante), nunca hardcodeada.
 */
class IAChatController extends Controller
{
    public function __construct(
        private ModuleRegistry $registry,
    ) {
    }

    /**
     * Sugerencias dinámicas para los chips de bienvenida del chat flotante — Fase 3 de #9.
     *
     * Derivadas de `example_intents` que cada módulo declara en su module.json (vía
     * ModuleRegistry::getAiContext()), NO hardcodeadas. Una sugerencia por módulo (en el
     * orden en que el registro los compila) hasta un máximo, para no saturar la UI.
     */
    public function suggestions(): JsonResponse
    {
        $picked = [];
        foreach ($this->registry->getAiContext() as $mod) {
            $intent = ($mod['example_intents'] ?? [])[0] ?? null;
            if ($intent !== null) {
                $picked[] = ['text' => $intent, 'module' => $mod['_module'] ?? null];
            }
            if (count($picked) >= 6) {
                break;
            }
        }

        return response()->json(['success' => true, 'suggestions' => $picked]);
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

        // Historial -> formato neutro de IA (filtra vacíos, normaliza rol).
        $historial = [];
        foreach ($data['history'] ?? [] as $turn) {
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $historial[] = [
                'rol'       => ($turn['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user',
                'contenido' => $content,
            ];
        }

        try {
            $r = IA::enviar('asistente.chat_flotante', $data['message'], [],
                $this->buildSystemPrompt($data['context'] ?? null), $historial, ['max_tokens' => 1024]);

            return response()->json([
                'success'       => true,
                'message'       => trim($r['texto']) !== '' ? trim($r['texto']) : 'No tengo una respuesta para eso en este momento.',
                'action_type'   => null,   // Fase 2: ejecución de acciones
                'action_result' => null,
            ]);
        } catch (IANoConfigurada $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            // Error VISIBLE: se loguea en servidor y se devuelve un mensaje claro
            // (no se traga el fallo; el frontend lo muestra inline).
            Log::error('[IAChat] fallo al responder: ' . $e->getMessage());

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
}
