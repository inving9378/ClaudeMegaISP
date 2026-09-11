<?php

namespace App\Modules\Addons\Ventas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Ventas\Models\VentaCustodia;

/**
 * UI mínima de solo lectura del item #9990780: ver el estado de custodia de un prospecto
 * (fecha límite, renovaciones usadas). No expone alta/renovación desde aquí (fuera de
 * alcance del item: "UI mínima para ver el estado").
 */
class CustodiaController extends Controller
{
    public function index()
    {
        $custodias = VentaCustodia::with(['prospecto', 'colaborador.user'])
            ->orderBy('fecha_limite')
            ->paginate(30);

        return view('addon-ventas::custodias.index', compact('custodias'));
    }
}
