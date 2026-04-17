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
        $roleDevelop = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();
        // Definir los permisos que deseas crear
        $permissions = [
            'company_information_view_company_information',
            'company_information_add_company_information',
            'company_information_edit_company_information',
            'company_information_delete_company_information',
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
                    $roleDevelop->givePermissionTo($permission);
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
        $roleDevelop = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();
        $permissions = [
            'company_information_view_company_information',
            'company_information_add_company_information',
            'company_information_edit_company_information',
            'company_information_delete_company_information',
        ];
        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();
            if ($permission) {
                $role->revokePermissionTo($permission);
                $roleDevelop->revokePermissionTo($permission);
                $permission->delete();
            }
        }
    }
};
