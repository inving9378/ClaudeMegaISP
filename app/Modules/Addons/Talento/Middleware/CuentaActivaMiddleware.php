<?php

namespace App\Modules\Addons\Talento\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * UNI-T B6 (#875): una cuenta con users.estado ≠ activo no usa la API de la app aunque su token siga
 * vigente. Detrás del interruptor unificacion.interruptores.cuenta_activa (apagado = sin efecto).
 */
class CuentaActivaMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && config('unificacion.interruptores.cuenta_activa') && $user->isNotActive()) {
            return response()->json(['message' => 'Tu cuenta está inactiva.'], 403);
        }

        return $next($request);
    }
}
