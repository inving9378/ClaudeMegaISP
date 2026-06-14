<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    public function index()
    {
        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        $servicios = DB::table('client_internet_services as cis')
            ->join('internets as i', 'i.id', 'cis.internet_id')
            ->where('cis.client_id', $clientId)
            ->whereNotIn('cis.estado', ['Eliminado'])
            ->orderByDesc('cis.id')
            ->select(
                'cis.id', 'i.title', 'i.download_speed', 'i.upload_speed', 'i.price',
                'cis.estado', 'cis.start_date', 'cis.finish_date', 'cis.ipv4',
                'cis.payment_type', 'cis.pay_period'
            )
            ->get();

        $fechaCorte = DB::table('clients')->where('id', $clientId)->value('fecha_pago');

        return view('addon-portal-cliente::plan', compact('cmi', 'servicios', 'fechaCorte'));
    }
}
