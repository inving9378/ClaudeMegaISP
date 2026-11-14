<?php

namespace App\Modules\Addons\Marketing\Middleware;

use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Models\EmpresaUsuario;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use App\Modules\Addons\Marketing\Support\EquipoMarketing;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `marketing.empresa`. Fija la empresa activa de la petición (va después de `auth`).
 *
 * CAMBIO DE ALCANCE de Henry (2026-11-13, puntos A y B de docs/marketing/fase-1-paquetes.md): por ahora
 * Marketing es SOLO de Meganet y SOLO entran sus administradores de Marketing. Acceso negado por defecto:
 *
 * 0. Usuario inactivo (`estado` ≠ 'activo') → cierra sesión y responde 401 `usuario_inactivo` (JSON) o
 *    manda al login (navegación). Es la revisión que hacía `check_route_permission`, que ya no va en las
 *    rutas del módulo (F1-1).
 * 0a. (Decisión de Irving, 2026-11-14) Quien tenga uno de los roles de `marketing.roles_con_acceso`
 *    (super-administrator, Administrador, DESARROLLADOR, Mostrador) entra como admin de Meganet, sin marca ni fila
 *    en el equipo. Los pasos 0b–2 quedan solo para quien NO tenga esos roles (acceso opcional por la lista).
 * 0b. Sin la MARCA `equipo_fijado_at` (la escriben la migración 2026_11_13_100200 y `marketing:equipo-fijar`) →
 *    403 `sin_acceso` para TODOS (§2.6.2): cubre el tramo entre la migración de Fase 0 que asigna por rol y la
 *    que restringe a la lista, y cualquier base donde esa migración no haya corrido.
 * 1. Sin ninguna asignación activa → 403 `sin_empresa` (JSON o la pantalla «Aún no tienes empresa»).
 * 2. Sin fila activa con `rol = 'admin'` en la empresa principal (Meganet, id 1) → 403 `sin_acceso`.
 *    Vale para todos: también super-administrador/DESARROLLADOR sin esa fila y las filas `marketing`/`ventas`.
 * 3. La empresa activa SIEMPRE es la principal, con rol `admin` (se ignora la elegida en sesión; el
 *    selector se oculta en pantalla). No se borra la lógica multiempresa de Fase 0 (tablas, scope, trait).
 * Al terminar la petición limpia el contexto (no se arrastra a lo que corra después de responder).
 */
class ResolverEmpresaActiva
{
    public const CLAVE_SESION = 'marketing.empresa_activa_id';

    public const MENSAJES = [
        'sin_empresa'       => 'No tienes acceso a Marketing. Pide acceso a un administrador de Marketing.',
        'sin_acceso'        => 'Marketing está abierto a super-administrador, Administrador, Desarrollador y Mostrador. Si necesitas entrar, pídele a un administrador que te asigne uno de esos roles.',
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

        if (($usuario->estado ?? 'activo') !== 'activo') {
            $this->contexto->limpiar();

            return self::usuarioInactivo($request);
        }

        // Decisión de Irving (2026-11-14): el acceso es por ROL. Quien tenga uno de `marketing.roles_con_acceso`
        // entra como admin de Meganet sin necesitar la marca ni su fila en el equipo.
        if (EquipoMarketing::tieneRolConAcceso($usuario)) {
            $principal = Empresa::query()->whereKey(Empresa::PRINCIPAL_ID)->where('activo', true)->first();
            if (! $principal) {
                $this->contexto->limpiar();

                return self::negativa($request, 'sin_empresa');
            }

            if ($request->hasSession()) {
                $request->session()->put(self::CLAVE_SESION, Empresa::PRINCIPAL_ID);
            }

            $this->contexto->establecer($principal, 'admin');

            return $next($request);
        }

        if (! EquipoMarketing::hayMarca()) {
            $this->contexto->limpiar();

            return self::negativa($request, 'sin_acceso');
        }

        $asignaciones = self::asignacionesActivas((int) $usuario->getKey());

        if ($asignaciones->isEmpty()) {
            $this->contexto->limpiar();

            return self::negativa($request, 'sin_empresa');
        }

        $admin = $asignaciones->first(
            fn (EmpresaUsuario $a) => (int) $a->company_id === Empresa::PRINCIPAL_ID && $a->rol === 'admin'
        );

        if (! $admin) {
            $this->contexto->limpiar();

            return self::negativa($request, 'sin_acceso');
        }

        if ($request->hasSession()) {
            $request->session()->put(self::CLAVE_SESION, Empresa::PRINCIPAL_ID);
        }

        $this->contexto->establecer($admin->empresa, 'admin');

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
     * Usuario inactivo: se cierra su sesión. API → 401 JSON; navegación → login.
     */
    public static function usuarioInactivo(Request $request): Response
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => false,
                'error'   => 'usuario_inactivo',
                'mensaje' => 'Tu usuario está inactivo.',
            ], 401);
        }

        return redirect()->route('login');
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

        // H-3 (QA Fase 2): en pantallas, quien no es admin de Meganet (sin_acceso) ve también «No tienes acceso a
        // Marketing», no el 403 genérico. La API sigue igual (JSON con su código y mensaje).
        $vista = in_array($codigo, ['sin_empresa', 'sin_acceso'], true) ? 'addon-marketing::sin-empresa' : 'meganet.pages.403';

        return response()->view($vista, ['mensaje' => self::MENSAJES[$codigo]], 403);
    }
}
