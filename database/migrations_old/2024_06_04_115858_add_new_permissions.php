<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AddNewPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $new_permissions = [
            'vendors_view_list',
            'ticket_new_add',
            'ticket_new_edit',
            'ticket_new_delete',
            'ticket_new_export',
            'ticket_closed_add',
            'ticket_closed_edit',
            'ticket_closed_delete',
            'ticket_closed_export',
            'ticket_recycled_add',
            'ticket_recycled_edit',
            'ticket_recycled_delete',
            'ticket_recycled_export',
            'finance_transactions_add',
            'finance_transactions_delete',
            'finance_transactions_export',
            'finance_invoices_edit',
            'finance_invoices_delete',
            'finance_invoices_export',
            'finance_payments_edit',
            'finance_payments_delete',
            'finance_payments_export',
            'maps_view_list',
            'config_view_config',
        ];

        foreach ($new_permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = Role::all();

        foreach ($roles as $role) {
            foreach ($new_permissions as $permission) {
                $role->givePermissionTo($permission);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $new_permissions = [
            'vendors_view_list',
            'ticket_new_add',
            'ticket_new_edit',
            'ticket_new_delete',
            'ticket_new_export',
            'ticket_closed_add',
            'ticket_closed_edit',
            'ticket_closed_delete',
            'ticket_closed_export',
            'ticket_recycled_add',
            'ticket_recycled_edit',
            'ticket_recycled_delete',
            'ticket_recycled_export',
            'finance_transactions_add',
            'finance_transactions_delete',
            'finance_transactions_export',
            'finance_invoices_edit',
            'finance_invoices_delete',
            'finance_invoices_export',
            'finance_payments_edit',
            'finance_payments_delete',
            'finance_payments_export',
            'maps_view_list',
            'config_view_config',
        ];
        
        $roles = Role::all();

        foreach ($roles as $role) {
            foreach ($new_permissions as $permission) {
                $role->revokePermissionTo($permission);
            }
        }

        foreach ($new_permissions as $permission) {
            Permission::where('name', $permission)->where('guard_name', 'web')->delete();
        }
    }
}
