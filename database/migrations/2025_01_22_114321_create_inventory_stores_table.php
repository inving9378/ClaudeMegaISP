<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\InventoryItem;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

        Schema::create('inventory_stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Crea un almacén genérico en el Seeder o Migration.
        DB::table('inventory_stores')->insert([
            'name' => 'Generic Store',
            'description' => 'Almacén genérico',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $module = Module::create([
            'name' => 'InventoryStore',
            'group' => 'Administration'
        ]);

        $fields = [
            [
                'name' => 'name',
                'label' => 'Nombre',
                'placeholder' => 'Nombre',
                'type' => 1,
                'position' => 1,
                'additional_field' => false,
            ],
            [
                'name' => 'description',
                'label' => 'Descripción',
                'placeholder' => 'Descripción',
                'type' => 5,
                'position' => 2,
                'additional_field' => false,
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
                'name' => 'name',
                'label' => 'Nombre',
                'order' => 2,
            ],
            [
                'name' => 'description',
                'label' => 'Descripción',
                'order' => 3,
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
            'inventory_store_view_inventory_store',
            'inventory_store_add_inventory_store',
            'inventory_store_edit_inventory_store',
            'inventory_store_delete_inventory_store'
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_store_id']);
            $table->dropColumn('inventory_store_id');
        });
        Schema::dropIfExists('inventory_stores');

        $role = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
        $role2 = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();
        $users = User::role($role->name)->get();
        $users2 = User::role($role2->name)->get();
        $permissions = [
            'inventory_store_view_inventory_store',
            'inventory_store_add_inventory_store',
            'inventory_store_edit_inventory_store',
            'inventory_store_delete_inventory_store'
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

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
};
