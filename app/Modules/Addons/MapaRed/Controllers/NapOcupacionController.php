<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Services\MapaRedNapOcupacionService;
use Illuminate\Http\Request;

/**
 * MR-20 (item roadmap #956) — semáforo de ocupación de puertos por NAP (D16). Gateado por el
 * mismo permiso `mapa_red_view` que el resto de `/mapa-red/api/**` (patrón de NapSaludController).
 */
class NapOcupacionController extends Controller
{
    public function __construct(private MapaRedNapOcupacionService $service)
    {
    }

    public function lote(Request $request)
    {
        $data = $request->validate([
            'puertable_type' => 'required|string',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        return response()->json(
            $this->service->calcularParaVarias($data['puertable_type'], $data['ids'])
        );
    }
}
