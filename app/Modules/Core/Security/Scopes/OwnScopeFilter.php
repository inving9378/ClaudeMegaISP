<?php

namespace App\Modules\Core\Security\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Item #865 (Fase B) — primer Eloquent Global Scope real del proyecto. Filtra
 * el modelo por `$ownerColumn` = usuario autenticado SOLO si tiene el permiso
 * `$permissionName` con scope='propios' asignado a alguno de sus roles en
 * `role_permission_scopes` (tabla de #865, distinta del catálogo declarativo
 * `permission_scopes` de #851). Sin fila explícita para ese (rol, permiso) →
 * no filtra nada, comportamiento idéntico al actual (scope='todos' es el
 * default de la tabla).
 */
class OwnScopeFilter implements Scope
{
    public function __construct(
        private readonly string $permissionName,
        private readonly string $ownerColumn = 'user_id',
    ) {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();
        if (!$user || !self::isActiveFor($user, $this->permissionName)) {
            return;
        }

        $builder->where($model->getTable() . '.' . $this->ownerColumn, $user->id);
    }

    /**
     * ¿Tiene este usuario, vía alguno de sus roles, scope='propios' asignado
     * para este permiso? Expuesto como estático para que controllers/vistas
     * consulten el mismo criterio sin duplicar la query (ej. mensaje de
     * "sin almacenes asignados" y ocultar el botón de crear).
     */
    public static function isActiveFor(User $user, string $permissionName): bool
    {
        if (!Schema::hasTable('role_permission_scopes')) {
            return false;
        }

        $permissionId = Permission::where('name', $permissionName)->where('guard_name', 'web')->value('id');
        if (!$permissionId) {
            return false;
        }

        $roleIds = $user->roles()->pluck('roles.id');
        if ($roleIds->isEmpty()) {
            return false;
        }

        return DB::table('role_permission_scopes')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $roleIds)
            ->where('scope', 'propios')
            ->exists();
    }
}
