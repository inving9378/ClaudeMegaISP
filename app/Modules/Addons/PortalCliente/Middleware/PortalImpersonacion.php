<?php

namespace App\Modules\Addons\PortalCliente\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Item #553 — «Entrar como este cliente». Si la sesión trae la marca `portal_impersonacion`
 * (puesta por Admin\ImpersonacionController), el portal funciona como el cliente PERO:
 *  - caduca (config portalcliente.impersonacion_minutos, 30 por defecto);
 *  - bloquea lo sensible: pagar, cambiar contraseña, ver CLABE/datos bancarios, login/registro/recuperar y logout
 *    (el logout invalidaría también la sesión del admin; para salir está «Volver al admin»).
 * No toca la sesión del admin (guard web): la salida solo cierra el guard `cliente`.
 */
class PortalImpersonacion
{
    private const BLOQUEADAS = [
        'facturas.pagar', 'perfil.password', 'pagos',
        'login.post', 'registro.post', 'recuperar.post', 'logout', 'openpay.webhook',
        'password.solicitar-otp', 'password.verificar-otp', 'password.restablecer',
    ];

    public function handle(Request $request, Closure $next)
    {
        $imp = $request->session()->get('portal_impersonacion');
        if (! $imp) {
            return $next($request);
        }

        $nombre = preg_replace('/^portal(_domain)?\./', '', (string) optional($request->route())->getName());

        if ($nombre === 'impersonacion.salir') {
            return $next($request);
        }

        if (now()->timestamp > (int) ($imp['hasta'] ?? 0) || ! Auth::guard('cliente')->check()) {
            return $this->terminar($request, $imp, 'La sesión de «entrar como cliente» caducó.');
        }

        if (in_array($nombre, self::BLOQUEADAS, true)) {
            if ($nombre === 'logout') {
                return $this->terminar($request, $imp, 'Saliste del portal del cliente.');
            }
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Acción no permitida mientras entras como el cliente.'], 403);
            }
            return redirect()->route('portal.dashboard')
                ->with('error', 'Esa acción no está permitida mientras entras como el cliente.');
        }

        return $next($request);
    }

    private function terminar(Request $request, array $imp, string $msg)
    {
        Auth::guard('cliente')->logout();
        $request->session()->forget('portal_impersonacion');
        activity('portal-impersonacion')
            ->causedBy(\App\Models\User::find($imp['admin_id'] ?? 0))
            ->withProperties(['client_id' => $imp['client_id'] ?? null, 'ip' => $request->ip()])
            ->log('Terminó «entrar como cliente»');

        return redirect('/cliente/editar/' . (int) ($imp['client_id'] ?? 0))->with('info', $msg);
    }
}
