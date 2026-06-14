<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PagosController extends Controller
{
    /**
     * Historial de pagos + CLABE del cliente.
     * SOLO LECTURA — sin OpenPay, sin "ya pagué".
     * Aislado por client_id (condición de paro #9 satisfecha).
     */
    public function index()
    {
        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        // Historial de pagos (polymorphic con Client)
        $pagos = DB::table('payments')
            ->where('paymentable_id', $clientId)
            ->whereIn('paymentable_type', [
                'App\\Models\\Client',
                'App\\Modules\\Core\\Clientes\\Models\\Client',
            ])
            ->whereNull('deleted_at')
            ->orderByRaw("COALESCE(STR_TO_DATE(date, '%d/%m/%Y'), date) DESC")
            ->limit(50)
            ->get(['id', 'date', 'amount', 'comment', 'receipt']);

        // CLABE asignada al cliente
        $clabes = DB::table('payment_clabes')
            ->where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->get(['id', 'clabe', 'is_active', 'created_at']);

        return view('addon-portal-cliente::pagos', compact('cmi', 'pagos', 'clabes'));
    }
}
