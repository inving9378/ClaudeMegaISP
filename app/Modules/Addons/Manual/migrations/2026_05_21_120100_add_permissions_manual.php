<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    /**
     * Permisos del módulo Manual de Usuario (addon-manual).
     * - manual_view        → visualizar /manual
     * - manual_generate    → ejecutar regeneración con Claude API (rol DESARROLLADOR)
     *
     * manual_view se concede a SUPER_ADMIN y DEVELOPER por defecto.
     * manual_generate se concede solo a DEVELOPER.
     */
    private array $permissionsView = [
        'manual_view',
    ];

    private array $permissionsDev = [
        'manual_generate',
    ];

    public function up(): void
    {
        $superAdmin = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
        $developer  = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();

        $superAdminUsers = $superAdmin ? User::role($superAdmin->name)->get() : collect();
        $developerUsers  = $developer  ? User::role($developer->name)->get()  : collect();

        foreach ($this->permissionsView as $permissionName) {
            $permission = $this->ensurePermission($permissionName);
            if ($superAdmin) {
                $superAdmin->givePermissionTo($permission);
            }
            if ($developer) {
                $developer->givePermissionTo($permission);
            }
            foreach ($superAdminUsers as $u) {
                $u->givePermissionTo($permission);
            }
            foreach ($developerUsers as $u) {
                $u->givePermissionTo($permission);
            }
        }

        foreach ($this->permissionsDev as $permissionName) {
            $permission = $this->ensurePermission($permissionName);
            if ($developer) {
                $developer->givePermissionTo($permission);
            }
            foreach ($developerUsers as $u) {
                $u->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        foreach (array_merge($this->permissionsView, $this->permissionsDev) as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();
            if ($permission) {
                $permission->delete();
            }
        }
    }

    private function ensurePermission(string $name): Permission
    {
        $permission = Permission::where('name', $name)
            ->where('guard_name', 'web')
            ->first();

        if ($permission) {
            return $permission;
        }

        try {
            return Permission::create(['name' => $name, 'guard_name' => 'web']);
        } catch (PermissionAlreadyExists $e) {
            return Permission::where('name', $name)->where('guard_name', 'web')->first();
        }
    }
};
