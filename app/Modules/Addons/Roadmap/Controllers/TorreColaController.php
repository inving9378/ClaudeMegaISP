<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Services\ColaCircuitoService;
use App\Modules\Addons\Roadmap\Services\CuotaPlanService;
use App\Modules\Addons\Roadmap\Services\RitmoCircuitoService;
use Illuminate\Http\JsonResponse;

/**
 * Limpieza de la Hoja de ruta — «Cola: N tomables · M bloqueados (por qué) · K en curso» en la Torre,
 * con el ritmo vigente y lo que solo Irving puede destrabar. Misma cola que el despachador y Jarvis.
 */
class TorreColaController extends Controller
{
    public function __invoke(ColaCircuitoService $cola, RitmoCircuitoService $ritmo, CuotaPlanService $cuota): JsonResponse
    {
        $this->authorize('roadmap_view');

        return response()->json([
            'ok'    => true,
            'cola'  => $cola->resumen(),
            'ritmo' => $ritmo->ultimo() ?? $ritmo->limite(),
            'cuota' => $cuota->leer(),
        ]);
    }
}
