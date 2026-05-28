<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $role  = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
        $role2 = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();
        $users  = User::role($role->name)->get();
        $users2 = User::role($role2->name)->get();

        $permissions = [
            'inventory_supplier_view_supplier',
            'inventory_supplier_add_supplier',
            'inventory_supplier_edit_supplier',
            'inventory_supplier_delete_supplier',
            'inventory_supplier_invoice_view_supplier_invoice',
            'inventory_supplier_invoice_add_supplier_invoice',
            'inventory_supplier_invoice_edit_supplier_invoice',
            'inventory_supplier_invoice_delete_supplier_invoice',
            'inventory_supplier_vendors_view_supplier_vendors',
            'inventory_supplier_vendors_add_supplier_vendors',
            'inventory_supplier_vendors_edit_supplier_vendors',
            'inventory_supplier_vendors_delete_supplier_vendors',
            'inventory_valuation_view_inventory_valuation',
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();

            if (!$permission) {
                try {
                    $permission = Permission::create([
                        'name'       => $permissionName,
                        'guard_name' => 'web',
                    ]);

                    $role->givePermissionTo($permission);
                    $role2->givePermissionTo($permission);
                    foreach ($users as $user) {
                        $user->givePermissionTo($permission);
                    }
                    foreach ($users2 as $user) {
                        $user->givePermissionTo($permission);
                    }
                } catch (PermissionAlreadyExists $e) {
                    echo "El permiso `{$permissionName}` ya existe para el guard `web`.";
                }
            } else {
                $role->givePermissionTo($permission);
                $role2->givePermissionTo($permission);
                foreach ($users as $user) {
                    $user->givePermissionTo($permission);
                }
                foreach ($users2 as $user) {
                    $user->givePermissionTo($permission);
                }
            }
        }
    }

    public function down(): void {}
};