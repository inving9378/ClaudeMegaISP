<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $superAdmin = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
        $developer = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();

        $permissions = [
            'admin_modules',
            'admin_modules_migrate',
            'admin_modules_toggle',
        ];

        foreach ($permissions as $name) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if (! $permission) {
                $permission = Permission::create(['name' => $name, 'guard_name' => 'web']);
            }
            // Sólo SUPER_ADMIN y DESARROLLADOR pueden administrar módulos.
            foreach ([$superAdmin, $developer] as $role) {
                if ($role && ! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        // Sincroniza los caches de permisos de los usuarios con esos roles.
        $users = collect();
        if ($superAdmin) {
            $users = $users->merge(User::role($superAdmin->name)->get());
        }
        if ($developer) {
            $users = $users->merge(User::role($developer->name)->get());
        }
        foreach ($users->unique('id') as $user) {
            $user->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        foreach (['admin_modules', 'admin_modules_migrate', 'admin_modules_toggle'] as $name) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if ($permission) {
                $permission->delete();
            }
        }
    }
};
