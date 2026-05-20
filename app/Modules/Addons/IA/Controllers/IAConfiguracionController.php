<?php

namespace App\Modules\Addons\IA\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\IAPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class IAConfiguracionController extends Controller
{
    public function __construct(protected IAPricingService $pricing)
    {
    }

    public function index()
    {
        $this->data['url'] = 'meganet.module.ia';
        $this->data['module'] = 'IA';
        $this->data['notifications'] = $this->userNotification();

        $this->data['proveedores'] = IAProveedor::query()
            ->orderBy('nombre')
            ->get()
            ->map(fn ($p) => [
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
            ]);

        return view('addon-ia::configuracion', $this->data);
    }

    public function usoTokens(Request $request)
    {
        $userId = $request->integer('user_id') ?: auth()->id();
        $desde = $request->input('desde', Carbon::now()->subDays(30)->toDateString());
        $hasta = $request->input('hasta', Carbon::now()->toDateString());

        return response()->json([
            'success' => true,
            'desde' => $desde,
            'hasta' => $hasta,
            'resumen' => $this->pricing->resumenPorUsuario($userId, $desde, $hasta),
            'serie' => $this->pricing->porDia($userId, $desde, $hasta),
        ]);
    }
}
