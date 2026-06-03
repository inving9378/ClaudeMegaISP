<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Fuerza Accept: application/json en rutas API que no están en el grupo `api`
 * de Laravel. Sin esto, las excepciones (validación, autenticación) devuelven
 * un redirect 302 en vez de JSON.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        return $next($request);
    }
}
