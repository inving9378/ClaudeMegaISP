<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Permisos del Evaluador de Servicios Empresariales (panel staff).
     * Las rutas públicas viven en CheckRoutePermission::PUBLIC_ROUTES.
     */
    private array $permissions = [
        'evaluador_view_evaluacion',
        'evaluador_view_estadisticas',
        'evaluador_export_evaluacion',
    ];

    public function up(): void
    {
        $role = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
        $role2 = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();
        $users = $role ? User::role($role->name)->get() : collect();
        $users2 = $role2 ? User::role($role2->name)->get() : collect();

        foreach ($this->permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();

            if (!$permission) {
                try {
                    $permission = Permission::create([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                    ]);
                } catch (PermissionAlreadyExists $e) {
                    $permission = Permission::where('name', $permissionName)
                        ->where('guard_name', 'web')
                        ->first();
                }
            }

            if ($role) {
                $role->givePermissionTo($permission);
            }
            if ($role2) {
                $role2->givePermissionTo($permission);
            }
            foreach ($users as $user) {
                $user->givePermissionTo($permission);
            }
            foreach ($users2 as $user) {
                $user->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        $role = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
        $role2 = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();
        $users = $role ? User::role($role->name)->get() : collect();
        $users2 = $role2 ? User::role($role2->name)->get() : collect();

        foreach ($this->permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();

            if (!$permission) {
                continue;
            }

            if ($role) {
                $role->revokePermissionTo($permission);
            }
            if ($role2) {
                $role2->revokePermissionTo($permission);
            }
            foreach ($users as $user) {
                if ($user->hasPermissionTo($permission)) {
                    $user->revokePermissionTo($permission);
                }
            }
            foreach ($users2 as $user) {
                if ($user->hasPermissionTo($permission)) {
                    $user->revokePermissionTo($permission);
                }
            }

            $permission->delete();
        }
    }
};
