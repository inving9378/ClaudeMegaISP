<?php

namespace App\Modules\Addons\PortalCliente\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalCliente\Models\PortalClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Item #553 — Abre el portal ya logueado como el cliente, sin su contraseña (permiso `portal.impersonar`).
 * Abre solo el guard `cliente`; la sesión del admin (guard web) no se toca. Auditoría en activity_log.
 */
class ImpersonacionController extends Controller
{
    public function entrar(Request $request, int $clientId)
    {
        $cmi = PortalClient::where('client_id', $clientId)->firstOrFail();
        $admin = $request->user();

        Auth::guard('cliente')->login($cmi);
        $request->session()->put('portal_impersonacion', [
            'admin_id'  => $admin->id,
            'client_id' => $clientId,
            'hasta'     => now()->addMinutes((int) config('portalcliente.impersonacion_minutos', 30))->timestamp,
        ]);

        activity('portal-impersonacion')->causedBy($admin)
            ->withProperties(['client_id' => $clientId, 'ip' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 200)])
            ->log('Entró al portal como el cliente');

        return redirect()->route('portal.dashboard');
    }

    public function salir(Request $request)
    {
        $imp = $request->session()->get('portal_impersonacion');
        Auth::guard('cliente')->logout();
        $request->session()->forget('portal_impersonacion');

        if ($imp) {
            activity('portal-impersonacion')->causedBy($request->user('web'))
                ->withProperties(['client_id' => $imp['client_id'] ?? null, 'ip' => $request->ip()])
                ->log('Terminó «entrar como cliente»');
        }

        return redirect('/cliente/editar/' . (int) ($imp['client_id'] ?? 0));
    }
}
