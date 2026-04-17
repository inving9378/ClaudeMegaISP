<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $role = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
        $role2 = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();
        $users = User::role($role->name)->get();
        $users2 = User::role($role2->name)->get();

        $permissions = [
            'release_view_release',
            'release_add_release',
            'release_edit_release'
        ];
        foreach ($permissions as $permissionName) {
            // Verifica si el permiso existe con el nombre y el guard_name especificado
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();

            // Si no existe, lo crea
            if (!$permission) {
                try {
                    $permission = Permission::create([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                    ]);

                    $role->givePermissionTo($permission);
                    $role2->givePermissionTo($permission);
                    foreach ($users as $user) {
                        $user->givePermissionTo($permission);
                    }

                    foreach ($users2 as $user) {
                        $user->givePermissionTo($permission);
                    }
                } catch (PermissionAlreadyExists $e) {
                    // Maneja la excepción si ocurre
                    echo "El permiso `{$permissionName}` ya existe para el guard `web`.";
                }
            } else {
                $role->givePermissionTo($permission);
                $role2->givePermissionTo($permission);
                foreach ($users as $user) {
                    $user->givePermissionTo($permission);
                }

                foreach ($users2 as $user) {
                    $user->givePermissionTo($permission);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
