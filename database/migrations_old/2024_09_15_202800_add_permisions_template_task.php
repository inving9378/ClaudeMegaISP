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
            'templatetask_view_templatetask',
            'templatetask_add_templatetask',
            'templatetask_edit_templatetask',
            'templatetask_delete_templatetask',
            'templatetask_export_templatetask',

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
            'templatetask_view_templatetask',
            'templatetask_add_templatetask',
            'templatetask_edit_templatetask',
            'templatetask_delete_templatetask',
            'templatetask_export_templatetask',
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
