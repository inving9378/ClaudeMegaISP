<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Definir los permisos que deseas crear
        $permissions = [
            'task_view_task',
            'task_add_task',
            'task_edit_task',
            'task_delete_task',
            'task_export_task',
            'task_view_full_task'

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
         // Definir los permisos a eliminar
         $permissions = [
            'task_view_task',
            'task_add_task',
            'task_edit_task',
            'task_delete_task',
            'task_export_task',
            'task_view_full_task',
        ];

        // Obtener el rol de vendedor


        // Revocar y eliminar los permisos
        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission) {
                $permission->delete();
            }
        }
    }
};
