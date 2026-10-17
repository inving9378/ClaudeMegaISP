<?php

namespace App\Http\Middleware;

use Barryvdh\Debugbar\LaravelDebugbar;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja la barra de depuración (Debugbar) solo para el rol DESARROLLADOR.
 *
 * Debugbar guarda en storage/debugbar cada petición con sus cabeceras, la sesión
 * y los datos enviados. Encendida para todos, eso es una copia en disco de lo que
 * hace cada usuario del sistema. Con este middleware solo se guardan las
 * peticiones de un desarrollador con sesión iniciada; las demás (cualquier otro
 * usuario, visitantes, api, webhooks, 404) no dejan rastro.
 *
 * Va al FINAL de la pila global (lo agrega AppServiceProvider), o sea DENTRO de
 * InjectDebugbar: apagar Debugbar aquí, al volver la respuesta, basta para que
 * esa petición no se guarde ni se pinte la barra.
 *
 * Solo mira el usuario si alguien ya lo resolvió en la petición (hasUser): así no
 * dispara por su cuenta el login por cookie "recordarme" ni sus eventos. Las
 * pantallas del sistema pasan por el middleware 'auth', que siempre lo resuelve.
 */
class DebugbarSoloDesarrolladores
{
    public const ROL = 'DESARROLLADOR';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->esDesarrollador()) {
            app(LaravelDebugbar::class)->disable();
        }

        return $response;
    }

    private function esDesarrollador(): bool
    {
        $guard = Auth::guard('web');

        return $guard->hasUser() && $guard->user()->hasRole(self::ROL);
    }
}
