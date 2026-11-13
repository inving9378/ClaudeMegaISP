<?php

namespace App\Modules\Addons\Marketing\Middleware;

use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `marketing.principal`. La configuración compartida (Brand Kit, nichos, voz de nicho,
 * conexión con Meta) es una sola, la de Meganet: solo se cambia con Meganet como empresa activa.
 */
class SoloEmpresaPrincipal
{
    public function __construct(private readonly EmpresaActiva $contexto)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->contexto->id() !== Empresa::PRINCIPAL_ID) {
            return ResolverEmpresaActiva::negativa($request, 'config_compartida');
        }

        return $next($request);
    }
}
