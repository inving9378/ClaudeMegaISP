<?php

namespace App\Services\Asistente;

use App\Models\User;

/**
 * La MISMA regla que usan las pantallas (CheckRoutePermission): administradores por rol entran a
 * todo; los demás necesitan el permiso, ya sea directo o por su rol. Así JARVIS nunca puede hacer
 * más de lo que ese usuario haría con clics.
 */
class PermisosAsistente
{
    private static array $porUsuario = [];

    /** @param string|array|null $permiso basta con tener uno de la lista */
    public static function puede(User $user, string|array|null $permiso): bool
    {
        if ($permiso === null || $permiso === [] || $permiso === '') {
            return true;
        }
        if ($user->isAdmin() || $user->isDevelopment() || $user->isSuperAdmin()) {
            return true;
        }

        $mios = self::$porUsuario[$user->id] ??= $user->getAllPermissions()->pluck('name')->flip()->all();
        foreach ((array) $permiso as $p) {
            if (isset($mios[$p])) {
                return true;
            }
        }

        return false;
    }
}
