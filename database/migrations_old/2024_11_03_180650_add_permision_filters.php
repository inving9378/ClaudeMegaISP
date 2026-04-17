<?php

use App\Http\Controllers\Utils\ComunConstantsController;
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
        // Definir los permisos que deseas crear
        $permissions = [
            'task_filter_project',
            'task_filter_status',
            'task_filter_partner',
            'task_filter_assigned_to',
            'task_filter_filter',
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
                } catch (PermissionAlreadyExists $e) {
                    // Maneja la excepción si ocurre
                    echo "El permiso `{$permissionName}` ya existe para el guard `web`.";
                }
            }
        }

        $permissionsCalendar = [
            'calendar_filter_project',
            'calendar_filter_status',
            'calendar_filter_partner',
            'calendar_filter_assigned_to',
            'calendar_filter_filter',
        ];

        foreach ($permissionsCalendar as $permissionName) {
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
                } catch (PermissionAlreadyExists $e) {
                    // Maneja la excepción si ocurre
                    echo "El permiso `{$permissionName}` ya existe para el guard `web`.";
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $role = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
        $permissions = [
            'task_filter_project',
            'task_filter_status',
            'task_filter_partner',
            'task_filter_assigned_to',
            'task_filter_filter',
        ];
        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();
            if ($permission) {
                $role->revokePermissionTo($permission);
                $permission->delete();
            }
        }

        $permissionsCalendar = [
            'calendar_filter_project',
            'calendar_filter_status',
            'calendar_filter_partner',
            'calendar_filter_assigned_to',
            'calendar_filter_filter',
        ];

        foreach ($permissionsCalendar as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();
            if ($permission) {
                $role->revokePermissionTo($permission);
                $permission->delete();
            }
        }
    }
};
