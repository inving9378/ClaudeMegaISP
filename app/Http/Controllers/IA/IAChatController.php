<?php

namespace App\Http\Controllers\IA;

use App\Http\Controllers\Controller;
use App\Models\Asistente\Accion;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Core\ModuleManager\Services\ModuleRegistry;
use App\Services\Asistente\AsistenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * JARVIS — el asistente flotante (robotsito, IaChatFloat.vue).
 *
 * Memoria persistente por usuario, instrucciones y guía del sistema en resources/asistente/,
 * herramientas con los permisos del usuario y cambios que solo se aplican cuando el usuario los
 * confirma. Toda la lógica vive en App\Services\Asistente; aquí solo entra y sale HTTP.
 * La IA (integración + modelo) se elige en Integraciones → Módulos IA (clave asistente.chat_flotante).
 */
class IAChatController extends Controller
{
    public function __construct(
        private ModuleRegistry $registry,
        private AsistenteService $asistente,
    ) {
    }

    /**
     * Sugerencias para los chips de bienvenida, derivadas de `example_intents` de los módulos
     * activos (una por módulo, máximo 6).
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

    /** Historial del hilo actual del usuario (se pinta al abrir el chat). */
    public function conversacion(Request $request): JsonResponse
    {
        return response()->json(['success' => true] + $this->asistente->paraPantalla($request->user()));
    }

    public function nueva(Request $request): JsonResponse
    {
        $this->asistente->nuevaConversacion($request->user());

        return response()->json(['success' => true]);
    }

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string|max:4000',
            'context' => 'nullable|string|max:500',
        ]);

        try {
            $r = $this->asistente->responder($request->user(), $data['message'], mb_substr((string) ($data['context'] ?? ''), 0, 255) ?: null);

            return response()->json([
                'success'          => true,
                'message'          => $r['mensaje'],
                'accion_pendiente' => $r['accion_pendiente'] ?? null,
            ]);
        } catch (IANoConfigurada $e) {
            return response()->json(['success' => false, 'error' => 'JARVIS no tiene una IA asignada todavía. Un administrador debe elegirla en Integraciones → Módulos IA.']);
        } catch (\Throwable $e) {
            Log::error('[JARVIS] fallo al responder: ' . $e->getMessage(), ['user' => $request->user()?->id]);

            return response()->json(['success' => false, 'error' => 'No pude responder en este momento. Inténtalo de nuevo en un momento.']);
        }
    }

    public function confirmar(Request $request, int $id): JsonResponse
    {
        $accion = Accion::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $r = $this->asistente->confirmarAccion($request->user(), $accion);

        return response()->json(['success' => true, 'message' => $r['mensaje'], 'ok' => $r['ok'] ?? false]);
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $accion = Accion::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $r = $this->asistente->cancelarAccion($request->user(), $accion);

        return response()->json(['success' => true, 'message' => $r['mensaje']]);
    }
}
