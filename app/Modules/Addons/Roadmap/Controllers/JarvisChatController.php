<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\JarvisConversacion;
use App\Modules\Addons\Roadmap\Services\JarvisChatService;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
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

    /**
     * POST — #1068 «¿En qué nos quedamos?»: abre una conversación libre cuyo primer mensaje es el
     * resumen desde la última visita, y devuelve también las conversaciones anteriores (historial).
     */
    public function conversar(): JsonResponse
    {
        $this->authorize('roadmap_manage');
        $uid = (int) auth()->id();
        $r = $this->chat->abrirConversacionLibre($uid);

        return response()->json([
            'ok'           => true,
            'puede_crear_items' => $this->chat->puedeCrearItems(),
            'conversacion' => $this->presentar($r['conversacion']),
            'resumen'      => $r['resumen'],
            'desde'        => $r['desde'],
            // «Escuchar» usa la misma voz y velocidad que Irving eligió para la Torre (#424).
            'voz_tts'      => app(RoadmapCircuitoService::class)->getVozTts(),
            'rate_tts'     => app(RoadmapCircuitoService::class)->getRateTts(),
            'anteriores'   => array_values(array_filter(
                $this->chat->conversacionesRecientes($uid),
                fn ($c) => $c['id'] !== $r['conversacion']->id
            )),
        ]);
    }

    /** GET — #1113: la última conversación libre del usuario, sin crear nada. */
    public function ultima(): JsonResponse
    {
        $this->authorize('roadmap_manage');
        $c = $this->chat->ultimaConversacion((int) auth()->id());

        return response()->json([
            'ok'           => true,
            'conversacion' => $c ? $this->presentar($c) : null,
            'pendiente'    => $c ? $this->chat->tienePendiente($c) : false,
        ]);
    }

    /** GET — #1113: sus últimas 10 conversaciones con título. */
    public function recientes(): JsonResponse
    {
        $this->authorize('roadmap_manage');

        return response()->json(['ok' => true, 'conversaciones' => $this->chat->recientesConTitulo((int) auth()->id())]);
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

        return response()->json(['ok' => true, 'puede_crear_items' => $this->chat->puedeCrearItems(), 'conversacion' => $this->presentar($conversacion), 'pendiente' => $this->chat->tienePendiente($conversacion)]);
    }

    /** POST — manda un mensaje de Irving y devuelve la respuesta de Jarvis. */
    public function mensaje(Request $request, int $id): JsonResponse
    {
        $this->authorize('roadmap_manage');

        // #1113 — la respuesta se guarda aunque el navegador se desconecte a media llamada.
        ignore_user_abort(true);

        $datos        = $request->validate(['mensaje' => ['required', 'string', 'max:4000']]);
        $conversacion = JarvisConversacion::findOrFail($id);
        $contexto     = $this->chat->contextoValido($request->input('contexto'));

        try {
            $resultado = $this->chat->enviarMensaje($conversacion, $datos['mensaje'], $contexto);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'        => true,
            'user'      => ['rol' => 'irving', 'contenido' => $resultado['user']->contenido, 'created_at' => $resultado['user']->created_at],
            'assistant' => ['rol' => 'jarvis', 'contenido' => $resultado['assistant']->contenido, 'created_at' => $resultado['assistant']->created_at],
            'propuesta' => $resultado['propuesta'],
            'puede_crear_items' => $this->chat->puedeCrearItems(),
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
