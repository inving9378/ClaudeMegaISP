<?php

namespace App\Modules\Addons\VoIP\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\VoIP\Services\MegaVozKpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * MegaVoz Fase 5 — tablero de KPIs de la cola "Atención a Clientes".
 */
class MegaVozKpiController extends Controller
{
    public function __construct(private MegaVozKpiService $kpi)
    {
    }

    // ── Vista ────────────────────────────────────────────────────────────────

    public function vista()
    {
        if (! auth()->user()->can('voip.kpis.view')) {
            abort(403);
        }
        return view('addon-voip::kpis.index');
    }

    // ── API JSON ─────────────────────────────────────────────────────────────

    public function data(Request $request): JsonResponse
    {
        if (! auth()->user()->can('voip.kpis.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $queuename = $request->get('cola', 'cola_1');
        $desde = $request->filled('desde')
            ? Carbon::parse($request->get('desde'))->startOfDay()
            : now()->startOfDay();
        $hasta = $request->filled('hasta')
            ? Carbon::parse($request->get('hasta'))->endOfDay()
            : now()->endOfDay();

        return response()->json([
            'cola'       => $queuename,
            'desde'      => $desde->toDateString(),
            'hasta'      => $hasta->toDateString(),
            'resumen'    => $this->kpi->resumen($queuename, $desde, $hasta),
            'por_agente' => $this->kpi->porAgente($queuename, $desde, $hasta),
        ]);
    }
}
