<?php

namespace App\Modules\Addons\Marketing\Middleware;

use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `marketing.rol:<r1>,<r2>`. El rol de la persona EN LA EMPRESA ACTIVA debe estar en la lista.
 * Sin contexto → 403 `sin_empresa`; rol fuera de la lista → 403 `sin_permiso_rol`.
 */
class RequiereRolEmpresa
{
    public function __construct(private readonly EmpresaActiva $contexto)
    {
    }

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $this->contexto->hay()) {
            return ResolverEmpresaActiva::negativa($request, 'sin_empresa');
        }

        if (! in_array($this->contexto->rol(), $roles, true)) {
            return ResolverEmpresaActiva::negativa($request, 'sin_permiso_rol');
        }

        return $next($request);
    }
}
