<?php

namespace App\Http\Middleware;

use App\Models\ApiExternoClave;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Item #1159 — puerta de las consultas públicas (`/api/externo/*`): exige una clave vigente del
 * alcance pedido en `Authorization: Bearer <clave>` y limita las peticiones por clave.
 * Uso en rutas: `clave.externa:cobertura`. Sin sesión, sin cookies, siempre JSON.
 */
class ClaveApiExterna
{
    public function handle(Request $request, Closure $next, string $alcance)
    {
        $request->headers->set('Accept', 'application/json');

        $clave = ApiExternoClave::vigente($request->bearerToken(), $alcance);
        if (! $clave) {
            return response()
                ->json(['error' => 'no_autorizado', 'mensaje' => 'Falta la clave o no es válida.'], 401)
                ->header('WWW-Authenticate', 'Bearer');
        }

        $llave = 'api-externo-clave:' . $clave->getKey();
        $limite = max(1, (int) config('api_externo.limite_por_clave_minuto', 60));
        if (RateLimiter::tooManyAttempts($llave, $limite)) {
            $espera = RateLimiter::availableIn($llave);

            return response()
                ->json(['error' => 'demasiadas_peticiones', 'mensaje' => "Espera {$espera} segundos."], 429)
                ->header('Retry-After', (string) $espera);
        }
        RateLimiter::hit($llave, 60);

        $clave->registrarUso();
        $request->attributes->set('api_externo_clave', $clave);

        return $next($request);
    }
}
