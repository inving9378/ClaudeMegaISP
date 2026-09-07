<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Services\MapaRedCoberturaService;

/**
 * MR-26 Fase 1 (item roadmap #9990522) — capa GeoJSON de cobertura vendible. Gateado por el
 * mismo permiso `mapa_red_view` que el resto de `/mapa-red/api/**` (patrón de
 * NapOcupacionController/NapSaludController).
 */
class CoberturaController extends Controller
{
    public function __construct(private MapaRedCoberturaService $service)
    {
    }

    public function capa()
    {
        return response()->json($this->service->capaGeoJson());
    }
}
