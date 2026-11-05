<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Services\CuotaPlanService;
use Illuminate\Http\JsonResponse;

/** #1068 — indicador de cuota del plan de Claude en la cabecera de la Torre (solo lectura). */
class TorreCuotaPlanController extends Controller
{
    public function __invoke(CuotaPlanService $cuota): JsonResponse
    {
        $this->authorize('roadmap_view');

        return response()->json(['ok' => true, 'cuota' => $cuota->leer()]);
    }
}
