<?php

namespace App\Modules\Addons\VozMayorista\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    /**
     * Panel de Voz Mayorista.
     *
     * En el Bloque 1 solo monta la vista: el desglose de consumo contra el
     * mínimo del carrier —interno de Meganet, revendido a clientes y mínimo
     * sin aprovechar— necesita las tablas de consumo, que llegan con el
     * modelo de datos.
     */
    public function index(Request $request)
    {
        return view('voz-mayorista::panel');
    }
}
