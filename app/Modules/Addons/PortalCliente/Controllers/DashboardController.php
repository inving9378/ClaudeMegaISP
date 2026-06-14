<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        // ── Plan activo ──────────────────────────────────────────────────
        $plan = DB::table('client_internet_services as cis')
            ->join('internets as i', 'i.id', 'cis.internet_id')
            ->where('cis.client_id', $clientId)
            ->whereNotIn('cis.estado', ['Eliminado'])
            ->orderByDesc('cis.id')
            ->select('i.title', 'i.download_speed', 'i.upload_speed', 'cis.estado as estado_servicio', 'cis.start_date', 'cis.finish_date')
            ->first();

        // ── Saldo ────────────────────────────────────────────────────────
        $saldo = DB::table('balances')
            ->where('balanceable_id', $clientId)
            ->where('balanceable_type', 'App\\Models\\Client')
            ->value('amount') ?? 0;

        // ── Facturas vencidas ────────────────────────────────────────────
        $facturasVencidas = DB::table('client_invoices')
            ->where('client_id', $clientId)
            ->where('estado', 'Atrasado')
            ->selectRaw('COUNT(*) as conteo, COALESCE(SUM(total), 0) as monto')
            ->first();

        // ── Ticket abiertos ──────────────────────────────────────────────
        $ticketsAbiertos = DB::table('tickets')
            ->where('customer_lead', $clientId)
            ->whereNotIn('estado', ['Cerrado', 'Resuelto', 'Reciclado'])
            ->count();

        // ── Fecha de corte del cliente ───────────────────────────────────
        $fechaCorte = DB::table('clients')->where('id', $clientId)->value('fecha_pago');

        return view('addon-portal-cliente::dashboard', compact(
            'cmi', 'plan', 'saldo', 'facturasVencidas', 'ticketsAbiertos', 'fechaCorte'
        ));
    }
}
