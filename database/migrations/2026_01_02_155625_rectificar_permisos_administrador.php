<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Http\Controllers\Utils\ComunConstantsController as Constants;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Roles a los que queremos asignar los permisos
        $roles = [
            Constants::SUPER_ADMIN_ROLE,
            Constants::DEVELOPER_ROLE
        ];

        // Permisos a asignar
        $permissions = [
            'invoice_delete_invoice',
        ];

        foreach ($permissions as $permissionName) {
            // Crear permiso si no existe
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web']
            );

            foreach ($roles as $roleName) {
                // Traer o crear rol si no existe
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

                // Asignar permiso al rol
                $role->givePermissionTo($permission);

                // Asignar permiso a todos los usuarios con ese rol
                User::role($roleName)->get()->each(function ($user) use ($permission) {
                    $user->givePermissionTo($permission);
                });
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
