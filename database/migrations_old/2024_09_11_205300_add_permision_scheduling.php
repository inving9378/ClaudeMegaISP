<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Definir los permisos que deseas crear
        $permissions = [
            'scheduling_view_scheduling',
            'scheduling_project_view_project',
            'scheduling_project_create',
            'scheduling_project_update',
            'scheduling_project_delete',

            'scheduling_task_view_task',
            'scheduling_task_create',
            'scheduling_task_update',
            'scheduling_task_delete',

            'scheduling_view_calendar',

        ];

        // Obtener el rol de vendedor
        $role = Role::where('name', ComunConstantsController::SELLER_ROLE)->first();

        // Crear y asignar los permisos al rol
        foreach ($permissions as $permissionName) {
            $permission = Permission::create([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);

            $role->givePermissionTo($permission);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Definir los permisos a eliminar
        $permissions = [
            'scheduling_view_scheduling',
            'scheduling_project_view_project',
            'scheduling_project_create',
            'scheduling_project_update',
            'scheduling_project_delete',

            'scheduling_task_view_task',
            'scheduling_task_create',
            'scheduling_task_update',
            'scheduling_task_delete',

            'scheduling_view_calendar',


        ];

        // Obtener el rol de vendedor
        $role = Role::where('name', ComunConstantsController::SELLER_ROLE)->first();

        // Revocar y eliminar los permisos
        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission) {
                $role->revokePermissionTo($permission);
                $permission->delete();
            }
        }
    }
};
