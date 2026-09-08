<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Services\MapaRedCoberturaService;
use Illuminate\Http\Request;

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

    /**
     * MR-26 Fase 2 (item roadmap #9990577) — "¿hay cobertura vendible en este punto?". El
     * geocoding dirección→lat/lng se resuelve en el navegador (Google Maps JS Geocoder ya
     * disponible vía MIX_VUE_APP_GOOGLEMAPS_KEY), no server-side.
     */
    public function consultar(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        return response()->json($this->service->consultarPunto((float) $request->lat, (float) $request->lng));
    }
}
