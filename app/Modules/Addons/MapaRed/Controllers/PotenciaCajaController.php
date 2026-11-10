<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Services\PotenciaCajaService;
use Illuminate\Http\Request;

/** Item #1235 — potencia por cliente y de la caja de servicio. */
class PotenciaCajaController extends Controller
{
    public function __construct(private PotenciaCajaService $service)
    {
    }

    public function show(Request $request, int $layerId)
    {
        return response()->json(
            $this->service->calcular($layerId, !$request->boolean('sin_vivo'))
        );
    }

    public function corregirInicial(Request $request, int $portId)
    {
        $data = $request->validate(['potencia_inicial_dbm' => 'nullable|numeric|between:-50,0']);
        $puerto = $this->service->corregirInicial($portId, $data['potencia_inicial_dbm'] ?? null);

        return response()->json(['id' => $puerto->id, 'potencia_inicial_dbm' => $puerto->potencia_inicial_dbm]);
    }
}
