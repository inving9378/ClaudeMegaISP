<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
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
        $module = Module::where('name', 'Invoice')->first();
        if ($module) {
            $module->packages()->detach();
            $module->columnsDatatable()->delete();
            $module->delete();
        }

        $module = Module::create([
            'name' => 'Invoice',
            'group' => 'Administration'
        ]);

        $columnsDatatable = [
            [
                'name' => 'id',
                'label' => 'ID',
                'order' => 1,
            ],
            [
                'name' => 'number',
                'label' => 'Número',
                'order' => 2,
            ],
            [
                'name' => 'client_id',
                'label' => 'Cliente',
                'order' => 4,
            ],
            [
                'name' => 'period',
                'label' => 'Periodo',
                'order' => 5,
            ],
            [
                'name' => 'due_date',
                'label' => 'Fecha de vencimiento',
                'order' => 6,
            ],
            [
                'name' => 'payment_date',
                'label' => 'Fecha de pago',
                'order' => 7,
            ],

            [
                'name' => 'total',
                'label' => 'Total',
                'order' => 8,
            ],

            [
                'name' => 'subtotal',
                'label' => 'Subtotal',
                'order' => 9,
            ],
            [
                'name' => 'tax',
                'label' => 'Impuestos',
                'order' => 10,
            ],

            [
                'name' => 'pending_balance',
                'label' => 'Saldo pendiente',
                'order' => 11,
            ],
            [
                'name' => 'status',
                'label' => 'Estado',
                'order' => 12,
            ],
            [
                'name' => 'payment_method',
                'label' => 'Método de pago',
                'order' => 13,
            ],
            [
                'name' => 'type',
                'label' => 'Tipo de factura',
                'order' => 14,
            ],
            [
                'name' => 'notes',
                'label' => 'Observaciones',
                'order' => 15,
            ],
            [
                'name' => 'transaction_id',
                'label' => 'ID de transacción',
                'order' => 16,
            ],
            [
                'name' => 'payment_id',
                'label' => 'ID de pago',
                'order' => 17,
            ],
            [
                'name' => 'created_at',
                'label' => 'Fecha de creación',
                'order' => 18,
            ],
            [
                'name' => 'action',
                'filter_name' => null,
                'label' => "Acciones",
                'order' => 999
            ],
        ];
        $module->columnsDatatable()->createMany($columnsDatatable);

        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $ckeditor = [23];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor]);
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);

        //Permisos de las rutas
        $role = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
        $role2 = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();
        $users = User::role($role->name)->get();
        $users2 = User::role($role2->name)->get();
        // Definir los permisos que deseas crear
        $permissions = [
            'finance_view_invoices',
            'invoice_view_invoice',
            'invoice_add_invoice',
            'invoice_edit_invoice',
            'invoice_delete_invoice'
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
                    $role2->givePermissionTo($permission);
                } catch (PermissionAlreadyExists $e) {
                    // Maneja la excepción si ocurre
                    echo "El permiso `{$permissionName}` ya existe para el guard `web`.";
                }
            }
        }

        foreach ($users as $user) {
            // Añadir permisos al usuario
            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)
                    ->where('guard_name', 'web')
                    ->first();
                if (!$permission) {
                    $permission = Permission::create([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                    ]);
                }
                $user->givePermissionTo($permission);
            }
        }

        foreach ($users2 as $user) {
            // Añadir permisos al usuario
            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)
                    ->where('guard_name', 'web')
                    ->first();
                if (!$permission) {
                    $permission = Permission::create([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                    ]);
                }
                $user->givePermissionTo($permission);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Invoice')->first();
        $module->packages()->detach();
        $module->columnsDatatable()->delete();
        $module->delete();
    }
};
