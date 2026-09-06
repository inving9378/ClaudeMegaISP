<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Services\MapaRedNapHealthService;
use Illuminate\Http\Request;

/**
 * MR-21 (item roadmap #957) — semáforo de salud por NAP + dashboard al hacer clic, alimentado
 * por MultiOLT (D18). Gateado por el mismo permiso `mapa_red_view` que el resto de
 * `/mapa-red/api/**` (patrón ya declarado en config/route_permission.php).
 */
class NapSaludController extends Controller
{
    public function __construct(private MapaRedNapHealthService $service)
    {
    }

    public function dashboard(Request $request)
    {
        $data = $request->validate([
            'puertable_type' => 'required|string',
            'puertable_id' => 'required|integer',
        ]);

        return response()->json(
            $this->service->calcular($data['puertable_type'], (int) $data['puertable_id'])
        );
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
