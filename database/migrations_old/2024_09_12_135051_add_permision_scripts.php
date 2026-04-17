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
            'admin_view_script'
        ];

        // Obtener el rol de vendedor


        // Crear y asignar los permisos al rol
        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', 'admin_view_script')->first();



        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Definir los permisos a eliminar
        $permissions = [
            'admin_view_script'
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
