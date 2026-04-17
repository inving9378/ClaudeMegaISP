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
            'client_document_view_client',
            'client_document_add_client',
            'client_document_edit_client',
            'client_document_delete_client',
            'client_document_view_tab_client',
            'client_billing_view_tab_client',
            'client_billing_transaction_client',
            'client_billing_transaction_add',
            'client_billing_transaction_edit',
            'client_billing_transaction_delete',
            'client_billing_invoice_client',
            'client_billing_invoice_add',
            'client_billing_invoice_edit',
            'client_billing_invoice_delete',
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
            'client_document_view_client',
            'client_document_add_client',
            'client_document_edit_client',
            'client_document_delete_client',
            'client_document_view_tab_client',
            'client_billing_view_tab_client',
            'client_billing_transaction_client',
            'client_billing_transaction_add',
            'client_billing_transaction_edit',
            'client_billing_transaction_delete',
            'client_billing_invoice_client',
            'client_billing_invoice_add',
            'client_billing_invoice_edit',
            'client_billing_invoice_delete',

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
