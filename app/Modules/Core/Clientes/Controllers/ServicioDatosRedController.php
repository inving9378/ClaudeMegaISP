<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Metricas\DatosRedDedicado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Botón «Datos de red» de un servicio de internet dedicado (item #988).
 * Ver: el permiso de siempre de la ficha (client_view_client). Guardar: además
 * clientes.datos_red.editar. Nunca guarda el servicio: solo sus tablas propias.
 */
class ServicioDatosRedController extends Controller
{
    public const PERMISO_EDITAR = 'clientes.datos_red.editar';

    public function __construct(private readonly DatosRedDedicado $datosRed)
    {
    }

    /** GET /cliente/servicio-internet/{servicioId}/datos-red */
    public function show(Request $request, int $servicioId): JsonResponse
    {
        return response()->json($this->datosRed->para($servicioId) + ['puede_editar' => $this->puedeEditar($request)]);
    }

    /** POST /cliente/servicio-internet/{servicioId}/datos-red */
    public function guardar(Request $request, int $servicioId): JsonResponse
    {
        abort_unless($this->puedeEditar($request), 403, 'No tienes permiso para editar los datos de red del dedicado.');

        return response()->json($this->datosRed->guardar($servicioId, $request->only([
            'router_id', 'interfaz_vlan', 'vlan_id', 'bloque_cidr', 'ip_router', 'activo', 'ips',
        ])) + ['puede_editar' => true]);
    }

    private function puedeEditar(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && ($user->can(self::PERMISO_EDITAR) || $user->isAdmin());
    }
}
