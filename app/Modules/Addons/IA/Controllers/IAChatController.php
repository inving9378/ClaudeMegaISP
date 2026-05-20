<?php

namespace App\Modules\Addons\IA\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Models\IAConversacion;
use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Models\IAProyecto;
use App\Modules\Addons\IA\Services\IAProveedorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class IAChatController extends Controller
{
    public function __construct(protected IAProveedorService $service)
    {
        $this->data['url'] = 'meganet.module.ia';
        $this->data['module'] = 'IA';
    }

    public function index()
    {
        $this->data['notifications'] = $this->userNotification();

        $this->data['proveedores'] = IAProveedor::query()
            ->orderBy('nombre')
            ->get()
            ->makeVisible([])
            ->map(fn ($p) => $this->serializarProveedor($p, withSecret: false));

        $this->data['proyectos'] = IAProyecto::query()
            ->where(fn ($q) => $q->where('user_id', auth()->id())->orWhere('es_default', true))
            ->orderByDesc('es_default')
            ->orderBy('nombre')
            ->get();

        return view('addon-ia::index', $this->data);
    }

    public function enviar(Request $request, $conversacionId)
    {
        $validator = Validator::make($request->all(), [
            'mensaje' => ['required', 'string'],
            'imagenes' => ['nullable', 'array', 'max:' . IAProveedorService::MAX_IMAGENES_POR_MENSAJE],
            'imagenes.*.mime' => ['required_with:imagenes', 'string'],
            'imagenes.*.data' => ['required_with:imagenes', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $conversacion = IAConversacion::with('proveedor')->findOrFail($conversacionId);

        try {
            $mensajes = $this->service->enviarMensaje(
                $conversacion,
                $request->input('mensaje'),
                $request->input('imagenes', [])
            );

            return response()->json([
                'success' => true,
                'user' => $mensajes['user'],
                'assistant' => $mensajes['assistant'],
                'conversacion' => $conversacion->fresh(),
            ]);
        } catch (\Throwable $e) {
            Log::error('IA enviar mensaje: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function serializarProveedor(IAProveedor $p, bool $withSecret = false): array
    {
        return [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'driver' => $p->driver,
            'endpoint_url' => $p->endpoint_url,
            'modelo_default' => $p->modelo_default,
            'soporta_imagenes' => (bool) $p->soporta_imagenes,
            'headers_personalizados' => $p->headers_personalizados,
            'config_extra' => $p->config_extra,
            'activo' => (bool) $p->activo,
            'estado' => $p->estado,
            'ultimo_error' => $p->ultimo_error,
            'probado_at' => $p->probado_at,
            'tiene_api_key' => !empty($p->getRawOriginal('api_key')),
            'api_key' => $withSecret ? $p->api_key : null,
        ];
    }
}
