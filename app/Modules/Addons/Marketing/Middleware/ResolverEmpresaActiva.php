<?php

namespace App\Modules\Addons\Marketing\Middleware;

use App\Modules\Addons\Marketing\Models\EmpresaUsuario;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `marketing.empresa`. Fija la empresa activa de la petición (va después de `auth`).
 *
 * 1. Toma las asignaciones activas del usuario cuya empresa está activa.
 * 2. Ninguna → 403 `sin_empresa` (JSON o la pantalla «Aún no tienes empresa en Marketing»).
 * 3. Si la empresa guardada en sesión es una de ellas, esa; si no, la de menor id (y se guarda).
 * 4. EmpresaActiva::establecer($empresa, $rol).
 * Al terminar la petición limpia el contexto (no se arrastra a lo que corra después de responder).
 */
class ResolverEmpresaActiva
{
    public const CLAVE_SESION = 'marketing.empresa_activa_id';

    public const MENSAJES = [
        'sin_empresa'       => 'Todavía no perteneces a ninguna empresa de Marketing. Pide a un administrador de Marketing que te agregue en «Empresas y equipo».',
        'sin_permiso_rol'   => 'Tu rol en esta empresa no permite esta acción.',
        'config_compartida' => 'Esta configuración es de Meganet y la comparten todas las empresas; solo se cambia con Meganet como empresa activa.',
    ];

    public function __construct(private readonly EmpresaActiva $contexto)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            throw new AuthenticationException();
        }

        $asignaciones = self::asignacionesActivas((int) $usuario->getKey());

        if ($asignaciones->isEmpty()) {
            $this->contexto->limpiar();

            return self::negativa($request, 'sin_empresa');
        }

        $elegida = null;
        if ($request->hasSession()) {
            $enSesion = $request->session()->get(self::CLAVE_SESION);
            if (is_numeric($enSesion)) {
                $elegida = $asignaciones->firstWhere('company_id', (int) $enSesion);
            }
        }

        if (! $elegida) {
            $elegida = $asignaciones->first();
            if ($request->hasSession()) {
                $request->session()->put(self::CLAVE_SESION, $elegida->company_id);
            }
        }

        $this->contexto->establecer($elegida->empresa, $elegida->rol);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        app(EmpresaActiva::class)->limpiar();
    }

    /**
     * Asignaciones activas de un usuario en empresas activas, por id de empresa ascendente.
     *
     * @return \Illuminate\Support\Collection<int, EmpresaUsuario>
     */
    public static function asignacionesActivas(int $userId)
    {
        return EmpresaUsuario::query()
            ->with('empresa')
            ->where('user_id', $userId)
            ->where('activo', true)
            ->whereIn('rol', EmpresaUsuario::ROLES)
            ->whereHas('empresa', fn ($q) => $q->where('activo', true))
            ->orderBy('company_id')
            ->get();
    }

    /**
     * Respuesta 403 de la capa por empresa: JSON para peticiones de API, página para navegación.
     */
    public static function negativa(Request $request, string $codigo): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => false,
                'error'   => $codigo,
                'mensaje' => self::MENSAJES[$codigo],
            ], 403);
        }

        $vista = $codigo === 'sin_empresa' ? 'addon-marketing::sin-empresa' : 'meganet.pages.403';

        return response()->view($vista, ['mensaje' => self::MENSAJES[$codigo]], 403);
    }
}
