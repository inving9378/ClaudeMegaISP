<?php

namespace App\Support\Seguridad;

use App\Models\User;

/**
 * Quién cuenta como super-administrador para las pantallas que lo exigen («Envíos automáticos»,
 * «Correcciones pendientes»): los dos nombres del rol que conviven en la base. DESARROLLADOR no
 * basta, aunque CheckRoutePermission lo deje pasar.
 */
final class SuperAdministrador
{
    public const ROLES = ['super-administrator', 'Super Administrador'];

    public static function es(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(self::ROLES);
    }
}
