<?php

namespace App\Support\Manual;

use App\Models\User;

/**
 * Visibilidad por rol para una sección de manual — compartida entre los dos
 * sistemas de manual del sistema (App\Modules\Addons\Empresa\Models\ManualSection,
 * "Manual Operativo de Meganet", y App\Modules\Addons\Manual\Models\ManualSection,
 * "Manual de Usuario"). Antes vivía duplicada en el primero; se extrajo aquí al
 * llevar el capítulo Talento al segundo (2026-09-22) para que ambos apliquen
 * exactamente la misma regla sin mantener dos copias.
 *
 * Requiere que el modelo consumidor tenga una columna/atributo `visible_roles`
 * (cast a array, JSON nullable en BD).
 */
trait HasManualRoleVisibility
{
    /**
     * Roles que siempre ven el manual completo, sin importar visible_roles —
     * necesario para poder editar/curar el árbol entero desde un solo lugar.
     */
    public const ROLES_BYPASS = [
        'super-administrator',
        'Super Administrador',
        'Administrador',
        'DESARROLLADOR',
        'ADMINISTRADOR_COMPLETO',
    ];

    /** Roles asignables a visible_roles — para los que SÍ tiene sentido acotar (los bypass ya ven todo). */
    public const ROLES_ASSIGNABLE = [
        'Almacen',
        'CONTADOR',
        'Mostrador',
        'SUPERVISOR_MOSTRADOR',
        'TECNICO',
        'TECNICO_INSTALADOR',
        'TECNICO_PLANTA',
        'Vendedor',
        'conductor',
    ];

    /**
     * Pseudo-rol: "nadie del staff operativo, solo administración" — distinto de
     * visible_roles vacío/null (que significa "sin restricción, todos"). Sin este
     * sentinel no hay forma de expresar "esta sección es solo para quien administra
     * el sistema" sin dejarla abierta a todo el staff.
     */
    public const ROLE_ADMIN_ONLY = '__admin_only__';

    /** null/vacío = visible para cualquiera que pueda ver el manual. */
    public function isVisibleFor(?User $user): bool
    {
        if (empty($this->visible_roles)) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(self::ROLES_BYPASS)) {
            return true;
        }

        if (in_array(self::ROLE_ADMIN_ONLY, $this->visible_roles, true)) {
            return false;
        }

        return $user->hasAnyRole($this->visible_roles);
    }
}
