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

        // Trae lo nuevo de queue_log antes de calcular. Es incremental (guarda hasta dónde
        // leyó) y así los KPIs no dependen de que corra el cron de tareas programadas.
        // Se corre la clase directamente: el módulo solo registra sus comandos en consola, así
        // que Artisan::call() desde una petición web decía «the command does not exist».
        try {
            $importar = app(\App\Modules\Addons\VoIP\Console\ImportarQueueLogCommand::class);
            $importar->setLaravel(app());
            $importar->run(new \Symfony\Component\Console\Input\ArrayInput([]), new \Symfony\Component\Console\Output\NullOutput());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('VoIP: no se pudo importar queue_log para KPIs: ' . $e->getMessage());
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
