<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Metricas\EstadoServicioDedicado;
use App\Services\Metricas\HistoricoServicioDedicado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estadísticas → Internet de un cliente dedicado (item #988, Fase 4). Solo lectura, con el permiso
 * de ver la ficha (client_view_client). Sale de las tablas nuevas, no de ping_statistics (#999).
 */
class DedicadoMonitoreoController extends Controller
{
    public function __construct(private readonly EstadoServicioDedicado $estado)
    {
    }

    /** GET /cliente/statistics/dedicado/{id} — servicios del cliente y si tienen datos de red. */
    public function resumen(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        return response()->json($this->estado->resumen($id) + [
            'puede_editar_red' => $user !== null && ($user->can(ServicioDatosRedController::PERMISO_EDITAR) || $user->isAdmin()),
        ]);
    }

    /** GET /cliente/statistics/dedicado/servicio/{servicioId}/estado — tarjeta de tiempo real. */
    public function estado(int $servicioId): JsonResponse
    {
        return response()->json($this->estado->estado($servicioId));
    }

    /**
     * GET /cliente/statistics/dedicado/servicio/{servicioId}/historico?periodo=hoy|24h|7d|30d —
     * «Histórico del servicio»: series agregadas en el servidor, indicadores, eventos y origen.
     */
    public function historico(Request $request, int $servicioId, HistoricoServicioDedicado $historico): JsonResponse
    {
        $periodo = (string) $request->query('periodo', 'hoy');
        abort_unless(in_array($periodo, HistoricoServicioDedicado::PERIODOS, true), 422, 'Periodo no válido.');

        return response()->json($historico->historico($servicioId, $periodo));
    }
}
