<?php

namespace App\Http\Middleware;

use App\Support\RolInstancia;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra las rutas de un módulo que exige un rol de instalación concreto.
 *
 * Uso:  ->middleware('rol.instancia:operador')
 *
 * Es la SEGUNDA de las tres barreras del blindaje (la primera es el gate del
 * ciclo de vida, que impide activar el módulo; la tercera es el sidebar, que
 * ni siquiera muestra el menú). Existe por separado a propósito: si alguien
 * activa el módulo a mano en la base —saltándose el ciclo de vida— las rutas
 * siguen cerradas. Un blindaje de una sola capa es un blindaje que depende de
 * que nadie toque la base directamente, y eso no se sostiene.
 *
 * Devuelve 403 sin filtrar detalle del negocio: al operador de una instalación
 * de cliente no le decimos qué módulo existe del otro lado.
 */
class RequiereRolInstancia
{
    public function handle(Request $request, Closure $next, string $rolExigido): Response
    {
        if (! RolInstancia::satisface($rolExigido)) {
            abort(403, 'Este módulo no está disponible en esta instalación.');
        }

        return $next($request);
    }
}
