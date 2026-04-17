<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
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
        try {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventory_item_id');
                $table->foreign('inventory_item_id')->references('id')->on('inventory_items');
                $table->unsignedBigInteger('created_by');
                $table->foreign('created_by')->references('id')->on('users');
                $table->enum('type', ['Entrada', 'Salida']);
                $table->double('quantity');
                $table->longText('description')->nullable();
                $table->timestamps();
            });

            $module = Module::create([
                'name' => 'InventoryMovement',
                'group' => 'Administration'
            ]);

            $fields = [
                [
                    'name' => 'inventory_item_id',
                    'label' => 'Artículo',
                    'placeholder' => 'Seleccione Artículo',
                    'type' => 22,
                    'position' => 1,
                    'additional_field' => false,
                    'search' => json_encode([
                        'model' => 'App\Models\InventoryItem',
                        'id' => 'id',
                        'text' => 'name',
                    ])
                ],
                [
                    'name' => 'type',
                    'label' => 'Movimiento',
                    'placeholder' => 'Movimiento',
                    'type' => 22,
                    'position' => 2,
                    'additional_field' => false,
                    'options' => json_encode([
                        "Entrada" => "Entrada",
                        "Salida" => "Salida"
                    ])
                ],
                [
                    'name' => 'quantity',
                    'label' => 'Cantidad',
                    'placeholder' => 'Cantidad',
                    'type' => 15,
                    'position' => 3
                ],
                [
                    'name' => 'description',
                    'label' => 'Descripcion',
                    'placeholder' => 'Descripcion',
                    'type' => 5,
                    'position' => 4
                ],
            ];
            $module->fields()->createMany($fields);

            $columnsDatatable = [
                [
                    'name' => 'id',
                    'label' => 'ID',
                    'order' => 1,
                ],
                [
                    'name' => 'inventory_item_id',
                    'label' => 'Artículo',
                    'order' => 2,
                ],
                [
                    'name' => 'type',
                    'label' => 'Movimiento',
                    'order' => 3,
                ],
                [
                    'name' => 'quantity',
                    'label' => 'Cantidad',
                    'order' => 4,
                ],
                [
                    'name' => 'description',
                    'label' => 'Descripcion',
                    'order' => 5,
                ],
                [
                    'name' => 'created_at',
                    'label' => 'Creado',
                    'order' => 6,
                ],
                [
                    'name' => 'updated_at',
                    'label' => 'Actualizado',
                    'order' => 7,
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

            //
            //Permisos de las rutas
            $role = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
            $role2 = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();
            $users = User::role($role->name)->get();
            $users2 = User::role($role2->name)->get();
            // Definir los permisos que deseas crear
            $permissions = [
                'inventory_view_inventory',
                'inventory_movement_view_inventory_movement',
                'inventory_movement_add_inventory_movement',
                'inventory_movement_edit_inventory_movement',
                'inventory_movement_delete_inventory_movement'
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
                foreach ($permissions as $permission) {
                    $user->givePermissionTo($permission);
                }
            }

            foreach ($users2 as $user) {
                // Añadir permisos al usuario
                foreach ($permissions as $permission) {
                    $user->givePermissionTo($permission);
                }
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        $module = Module::where('name', 'InventoryMovement')->first();
        $module->fields()->delete();
        $module->columnsDatatable()->delete();
        $module->packages()->detach();
        $module->delete();

        $role = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
        $role2 = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();
        $users = User::role($role->name)->get();
        $users2 = User::role($role2->name)->get();
        $permissions = [
            'inventory_view_inventory',
            'inventory_movement_view_inventory_movement',
            'inventory_movement_add_inventory_movement',
            'inventory_movement_edit_inventory_movement',
            'inventory_movement_delete_inventory_movement'
        ];
        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();
            if ($permission) {
                $role->revokePermissionTo($permission);
                $role2->revokePermissionTo($permission);
                $permission->delete();
            }
        }

        foreach ($users as $user) {
            // Añadir permisos al usuario
            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)
                    ->where('guard_name', 'web')
                    ->first();
                if ($permission) {
                    $user->revokePermissionTo($permissionName);
                }
            }
        }

        foreach ($users2 as $user) {
            // Añadir permisos al usuario
            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)
                    ->where('guard_name', 'web')
                    ->first();
                if ($permission) {
                    $user->revokePermissionTo($permissionName);
                }
            }
        }
    }
};
