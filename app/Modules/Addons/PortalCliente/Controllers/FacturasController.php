<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FacturasController extends Controller
{
    /**
     * Lista de facturas del cliente autenticado.
     * Usa STR_TO_DATE para ordenar fechas VARCHAR DD/MM/YYYY correctamente.
     * Condición de aislamiento: client_id = cliente autenticado (satisface condición de paro #9).
     */
    public function index()
    {
        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        $facturas = DB::table('client_invoices')
            ->where('client_id', $clientId)
            ->orderByRaw("COALESCE(STR_TO_DATE(document_date, '%d/%m/%Y'), '1900-01-01') DESC")
            ->get([
                'id', 'number', 'total', 'estado',
                'payment_date', 'document_date', 'is_proforma',
            ]);

        return view('addon-portal-cliente::facturas', compact('cmi', 'facturas'));
    }

    /**
     * Detalle de una factura.
     * Verifica que client_id = cliente autenticado antes de devolver datos.
     */
    public function show(int $id)
    {
        $clientId = Auth::guard('cliente')->user()->client_id;

        $factura = DB::table('client_invoices')
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->first();

        if (! $factura) {
            abort(404);
        }

        return view('addon-portal-cliente::factura_show', compact('factura'));
    }
}
