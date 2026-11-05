<?php

namespace App\Modules\Core\Configuracion\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MetricasColectorLatido;
use App\Services\Metricas\AjusteMonitoreo;
use App\Services\Metricas\ConfiguracionMonitoreo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Configuración → «Monitoreo de dedicados» (item #988): el interruptor y cómo va el colector. */
class MonitoreoDedicadosController extends Controller
{
    public function index()
    {
        return view('core-configuracion::monitoreo-dedicados.index');
    }

    public function estado(ConfiguracionMonitoreo $configuracion): JsonResponse
    {
        return response()->json($this->respuesta($configuracion));
    }

    public function guardar(Request $request, ConfiguracionMonitoreo $configuracion): JsonResponse
    {
        $datos = $request->validate(['activo' => 'required|boolean']);
        AjusteMonitoreo::cambiar((bool) $datos['activo'], $request->user());

        return response()->json($this->respuesta($configuracion));
    }

    private function respuesta(ConfiguracionMonitoreo $configuracion): array
    {
        ['porRouter' => $porRouter, 'incompletos' => $incompletos] = $configuracion->dedicados();
        $latido = MetricasColectorLatido::orderByDesc('ts')->orderByDesc('id')->first();

        return AjusteMonitoreo::estado() + [
            'servicios_medidos' => array_sum(array_map('count', $porRouter)),
            'incompletos'       => $incompletos,
            'colector'          => $latido === null ? null : [
                'ultimo'   => $latido->ts->toIso8601String(),
                'segundos' => max(0, now()->diffInSeconds($latido->ts)),
                'routers'  => array_map(fn ($r) => [
                    'router_id' => $r['router_id'] ?? null,
                    'titulo'    => $r['titulo'] ?? null,
                    'estado'    => $r['estado'] ?? null,
                    'error'     => $r['error'] ?? null,
                ], (array) $latido->detalle),
            ],
        ];
    }
}
