<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsumoController extends Controller
{
    /**
     * Consumo de internet del cliente.
     * client_name = 'Meganet__' . client_id (patrón verificado en audit).
     * Aislado por client_name derivado de client_id autenticado.
     */
    public function index()
    {
        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;
        $clientName = 'Meganet__' . $clientId;

        $consumos = DB::table('internet_consumptions')
            ->where('client_name', $clientName)
            ->orderByDesc('date_recorded')
            ->limit(30)
            ->get(['id', 'date_recorded', 'bytes_in', 'bytes_out', 'uptime', 'ip_address']);

        $hayDatos = $consumos->isNotEmpty();

        return view('addon-portal-cliente::consumo', compact('cmi', 'consumos', 'hayDatos'));
    }
}
