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
     * Run the migrations.
     */
    public function up(): void
    {
        $role = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
        $role2 = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();
        $users = User::role($role->name)->get();
        $users2 = User::role($role2->name)->get();
        // Definir los permisos que deseas crear
        $permissions = [
            'maps_folder_add',
            'maps_folder_edit',
            'maps_folder_remove',
            'maps_route_add',
            'maps_route_edit',
            'maps_route_remove',
            'maps_service_box_add',
            'maps_service_box_edit',
            'maps_service_box_remove',
            'maps_junction_box_add',
            'maps_junction_box_edit',
            'maps_junction_box_remove',
            'maps_pack_add',
            'maps_pack_edit',
            'maps_pack_remove',
            'maps_cupboard_add',
            'maps_cupboard_edit',
            'maps_cupboard_remove',
            'maps_source_add',
            'maps_source_edit',
            'maps_source_remove',
            'maps_pole_add',
            'maps_pole_edit',
            'maps_pole_remove',
            'maps_building_add',
            'maps_building_edit',
            'maps_building_remove',
            'maps_client_add',
            'maps_client_edit',
            'maps_client_remove',
            'maps_note_add',
            'maps_note_edit',
            'maps_note_remove',
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
                } catch (PermissionAlreadyExists $e) {
                    // Maneja la excepción si ocurre
                    echo "El permiso `{$permissionName}` ya existe para el guard `web`.";
                }
            }
        }

        foreach ($users as $user) {
            // Añadir permisos al usuario
            foreach ($permissions as $permission) {
                $user->givePermissionTo($permission);
            }
        }

        foreach ($users2 as $user) {
            // Añadir permisos al usuario
            foreach ($permissions as $permission) {
                $user->givePermissionTo($permission);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $role = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
        $role2 = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();
        $users = User::role($role->name)->get();
        $users2 = User::role($role2->name)->get();
        $permissions = [
            'documentation_view_documentation'
        ];
        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();
            if ($permission) {
                $role->revokePermissionTo($permission);
                $role2->revokePermissionTo($permission);
                $permission->delete();
            }
        }

        foreach ($users as $user) {
            // Añadir permisos al usuario
            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)
                    ->where('guard_name', 'web')
                    ->first();
                if ($permission) {
                    $user->revokePermissionTo($permissionName);
                }
            }
        }

        foreach ($users2 as $user) {
            // Añadir permisos al usuario
            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)
                    ->where('guard_name', 'web')
                    ->first();
                if ($permission) {
                    $user->revokePermissionTo($permissionName);
                }
            }
        }
    }
};
