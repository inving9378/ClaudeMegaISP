<?php

namespace App\Modules\Core\Security\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Defensa en profundidad por ACCIÓN dentro de un controlador cuya ruta ya
 * pasó `check_route_permission` con un permiso más amplio (item roadmap #850,
 * Fase 4 de #843). Precedente: item #414, `OLTsOnuController` ya usa
 * `auth()->user()->can($permiso)` + abort inline por acción — este trait
 * generaliza ese patrón para módulos de dinero, pero con rollout log-only
 * (decisión q4 de Irving): mientras `config('permission_action_checks.enforce')`
 * sea false, un permiso faltante SOLO se registra en el log dedicado — la
 * acción sigue su curso normal. Pasar a enforcement real es cambiar ese
 * config (sin redeploy, vía env) — el mismo call site ya hace el abort(403).
 */
trait ChecksActionPermission
{
    protected function verificarPermisoAccion(string $permiso, ?string $contexto = null): bool
    {
        $user = auth()->user();
        $tienePermiso = (bool) ($user && $user->can($permiso));

        if (! $tienePermiso) {
            Log::channel(config('permission_action_checks.log_channel', 'single'))->warning(
                'Acción sin el permiso granular esperado (fase log-only, item #850)',
                [
                    'permiso'  => $permiso,
                    'user_id'  => $user?->id,
                    'ruta'     => request()->path(),
                    'metodo'   => request()->method(),
                    'contexto' => $contexto,
                    'enforced' => (bool) config('permission_action_checks.enforce', false),
                ]
            );

            if (config('permission_action_checks.enforce', false)) {
                abort(403, 'No autorizado.');
            }
        }

        return $tienePermiso;
    }
}
