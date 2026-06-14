<?php

namespace App\Modules\Addons\PortalCliente\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Verifica que hay un cliente autenticado en el guard 'cliente'.
 * Redirige a /portal/login si no lo hay.
 */
class AuthPortal
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('cliente')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No autenticado'], 401);
            }
            return redirect()->route('portal.login')
                ->with('info', 'Inicia sesión para acceder al portal.');
        }

        return $next($request);
    }
}
