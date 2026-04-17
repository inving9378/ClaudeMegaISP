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
        Schema::create('store_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->string('store_id');
            $table->timestamps();
        });


        $module = Module::create([
            'name' => 'StoreZone',
            'group' => 'Administration',
        ]);

        $fields = [
            [
                'name' => 'name',
                'label' => 'Nombre',
                'placeholder' => 'Nombre',
                'type' => 1,
                'position' => 1,
            ],
            [
                'name' => 'description',
                'label' => 'Descripción',
                'placeholder' => 'Descripción',
                'type' => 5,
                'position' => 3,
            ],
            [
                'name' => 'store_id',
                'label' => 'Almacén',
                'placeholder' => 'Seleccione un Almacén',
                'type' => 22,
                'position' => 2,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\InventoryStore',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],
        ];

        $columnsDatatable = [
            [
                'name' => 'id',
                'label' => 'ID',
                'order' => 1,
            ],
            [
                'name' => 'name',
                'label' => 'Name',
                'order' => 2,
            ],
            [
                'name' => 'description',
                'label' => 'Description',
                'order' => 3,
            ],
            [
                'name' => 'store_id',
                'label' => 'Store',
                'order' => 4,
            ],
            [
                'name' => 'action',
                'label' => 'Acciones',
                'order' => 10000,
            ],
        ];

        $module->fields()->createMany($fields);
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
            'store_zone_view_store_zone',
            'store_zone_add_store_zone',
            'store_zone_edit_store_zone',
            'store_zone_delete_store_zone'
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
        Schema::dropIfExists('store_zones');
        $module = Module::where('name', 'StoreZone')->first();
        $module->fields()->delete();
        $module->columnsDatatable()->delete();
        $module->packages()->detach();
        $module->delete();

        $role = Role::where('name', ComunConstantsController::SUPER_ADMIN_ROLE)->first();
        $role2 = Role::where('name', ComunConstantsController::DEVELOPER_ROLE)->first();
        $users = User::role($role->name)->get();
        $users2 = User::role($role2->name)->get();

        $permissions = [
            'store_zone_view_store_zone',
            'store_zone_add_store_zone',
            'store_zone_edit_store_zone',
            'store_zone_delete_store_zone'
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();
            if ($permission) {
                $role->revokePermissionTo($permission);
                $role2->revokePermissionTo($permission);
            }
        }

        foreach ($users as $user) {
            foreach ($permissions as $permission) {
                $user->revokePermissionTo($permission);
            }
        }

        foreach ($users2 as $user) {
            foreach ($permissions as $permission) {
                $user->revokePermissionTo($permission);
            }
        }
    }
};
