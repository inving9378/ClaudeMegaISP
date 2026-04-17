<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\Module;
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
            'seller_view_prospects',
            'seller_view_statics',
            'seller_view_sales',
            'seller_view_billing',
            'seller_follow_payment_client',
            'seller_view_all_payments_for_seller',
            'seller_view_all_transactions_for_seller'
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

        $permissions2 = [
            'crm_convert_crm'
        ];

        foreach ($permissions2 as $permissionName) {
            $permission = Permission::create([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Definir los permisos a eliminar
        $permissions = [
            'seller_view_prospects',
            'seller_view_statics',
            'seller_view_sales',
            'seller_view_billing',
            'seller_follow_payment_client',
            'seller_view_all_payments_for_seller',
            'seller_view_all_transactions_for_seller'
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

        $permissions2 = [
            'crm_convert_crm'
        ];

        foreach ($permissions2 as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission) {
                $permission->delete();
            }
        }
    }
};
