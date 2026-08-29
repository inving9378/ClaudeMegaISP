<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\JarvisConversacion;
use App\Modules\Addons\Roadmap\Services\JarvisChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Item #806 (Jarvis Parte 3b) — el chat donde Irving conversa el brief de una sugerencia de
 * Jarvis antes de encolarla como item. Vive bajo el mismo prefijo `api/roadmap` que el resto de
 * la Torre de Control, que NO usa `CheckRoutePermission` (esas rutas solo llevan `web`+`auth`,
 * ver `routes.php`) — el gate real es `$this->authorize()` explícito por método, igual que
 * `RoadmapController::store` (`roadmap_manage`) y el resto de los controllers de la Torre
 * (`JarvisIdentidadController`, `TorreFronterasController`). Se reusa `roadmap_manage` (ya
 * existe, ya asignado) en vez de crear un permiso nuevo: el chat es un paso previo a lo mismo
 * que ya gatea ese permiso (crear/gestionar items del roadmap).
 */
class JarvisChatController extends Controller
{
    public function __construct(private JarvisChatService $chat)
    {
    }

    /** GET — candidatos vivos del detector (#805) + el hilo ya abierto, si existe. */
    public function sugerencias(): JsonResponse
    {
        $this->authorize('roadmap_manage');

        return response()->json(['ok' => true] + $this->chat->sugerenciasConHilo());
    }

    /** POST — abre (o retoma) el hilo de una sugerencia. Idempotente por `sugerencia_clave`. */
    public function abrir(Request $request): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $datos = $request->validate([
            'clave'     => ['required', 'string', 'max:40'],
            'categoria' => ['nullable', 'string', 'max:60'],
            'texto'     => ['nullable', 'string'],
            'citas'     => ['nullable', 'array'],
        ]);

        $conversacion = $this->chat->obtenerOCrear(
            $datos['clave'],
            $datos['categoria'] ?? null,
            $datos['texto'] ?? null,
            $datos['citas'] ?? []
        );

        return response()->json(['ok' => true, 'conversacion' => $this->presentar($conversacion)]);
    }

    /** GET — hilo completo, para retomar una conversación ya abierta. */
    public function mostrar(int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $conversacion = JarvisConversacion::findOrFail($id);

        return response()->json(['ok' => true, 'conversacion' => $this->presentar($conversacion)]);
    }

    /** POST — manda un mensaje de Irving y devuelve la respuesta de Jarvis. */
    public function mensaje(Request $request, int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $datos        = $request->validate(['mensaje' => ['required', 'string', 'max:4000']]);
        $conversacion = JarvisConversacion::findOrFail($id);

        try {
            $resultado = $this->chat->enviarMensaje($conversacion, $datos['mensaje']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'        => true,
            'user'      => ['rol' => 'irving', 'contenido' => $resultado['user']->contenido, 'created_at' => $resultado['user']->created_at],
            'assistant' => ['rol' => 'jarvis', 'contenido' => $resultado['assistant']->contenido, 'created_at' => $resultado['assistant']->created_at],
        ]);
    }

    /**
     * POST — cierra el ciclo "chat → item encolado" (q4 del brief): NO crea el item aquí (eso
     * lo sigue haciendo, sin duplicarse, `RoadmapController::store` vía `POST /api/roadmap/items`
     * — el mismo botón "Agregar item" de la Hoja de Ruta). Este endpoint solo liga el hilo ya
     * convertido con el item resultante, para que el hilo quede marcado y no se repita.
     */
    public function vincularItem(Request $request, int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        $datos        = $request->validate(['item_id' => ['required', 'integer', 'exists:roadmap_items,id']]);
        $conversacion = JarvisConversacion::findOrFail($id);
        $this->chat->vincularItem($conversacion, (int) $datos['item_id']);

        return response()->json(['ok' => true, 'conversacion' => $this->presentar($conversacion->fresh())]);
    }

    private function presentar(JarvisConversacion $c): array
    {
        return [
            'id'               => $c->id,
            'sugerencia_clave' => $c->sugerencia_clave,
            'categoria'        => $c->categoria,
            'texto_sugerencia' => $c->texto_sugerencia,
            'citas'            => $c->citas,
            'item_id'          => $c->item_id,
            'estado'           => $c->estado,
            'mensajes'         => $c->mensajes->map(fn ($m) => [
                'rol'        => $m->rol,
                'contenido'  => $m->contenido,
                'created_at' => $m->created_at,
            ])->all(),
        ];
    }
}
