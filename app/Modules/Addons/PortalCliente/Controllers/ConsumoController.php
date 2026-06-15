<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsumoController extends Controller
{
    /**
     * Consumo de internet del cliente.
     *
     * Dos patrones coexisten en BD: 'Meganet{id}' (mayoría) y 'Meganet__{id}' (legado, ~10 distincts).
     * Ambos son seguros: igualdad exacta en whereIn, sin LIKE ni colisión entre clientes.
     * Aislamiento: los nombres se derivan del client_id del cliente autenticado.
     */
    public function index()
    {
        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        $names = ['Meganet' . $clientId, 'Meganet__' . $clientId];

        $consumos = DB::table('internet_consumptions')
            ->whereIn('client_name', $names)
            ->orderByDesc('date_recorded')
            ->limit(30)
            ->get(['id', 'date_recorded', 'bytes_in', 'bytes_out', 'uptime', 'ip_address']);

        $hayDatos = $consumos->isNotEmpty();

        return view('addon-portal-cliente::consumo', compact('cmi', 'consumos', 'hayDatos'));
    }
}
