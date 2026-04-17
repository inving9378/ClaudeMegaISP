<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = Module::create([
            'name' => 'InventoryMovementAll',
            'is_main' => false,
            'main' => 'InventoryMovement',
            'group' => 'Administracion',
        ]);
        $module->fields()->create([
            'name' =>  'store_to_store_enable',
            'type' => 18,
            'position' => 1,
            'label' => 'Cambiar de Almacen a Almacen',
            'depend' => 1,
            'inputs_depend' => json_encode([
                "store_from" => [
                    "field" => "store_from",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Almacen Origen",
                    "placeholder" => "Seleccione un Almacen",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "search" => [
                        "model" => "App\Models\InventoryStore",
                        "id" => "id",
                        "text" => "name",
                    ]
                ],
                "store_to" => [
                    "field" => "store_to",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Almacen Destino",
                    "placeholder" => "Seleccione un Almacen",
                    "position" => 2,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "search" => [
                        "model" => "App\Models\InventoryStore",
                        "id" => "id",
                        "text" => "name",
                    ]
                ],
            ]),
        ]);

        $module->fields()->create([
            'name' =>  'store_from',
            'type' => 30,
            'position' => 100,
            'label' => 'Almacen Origen',
        ]);

        $module->fields()->create([
            'name' =>  'store_to',
            'type' => 30,
            'position' => 100,
            'label' => 'Almacen Destino',
        ]);

        //store to user
        $module->fields()->create([
            'name' =>  'store_to_user_enable',
            'type' => 18,
            'position' => 1,
            'label' => 'Cambiar de Almacen a Usuario',
            'depend' => 1,
            'inputs_depend' => json_encode([
                "store_from" => [
                    "field" => "store_from",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Almacen Origen",
                    "placeholder" => "Seleccione un Almacen",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "search" => [
                        "model" => "App\Models\InventoryStore",
                        "id" => "id",
                        "text" => "name",
                    ]
                ],
                "user_to" => [
                    "field" => "user_to",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Usuario Destino",
                    "placeholder" => "Seleccione un Usuario",
                    "position" => 2,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "search" => [
                        "model" => "App\Models\User",
                        "id" => "id",
                        "text" => "name",
                        "scope" => "notClientRole",
                    ]
                ],
            ]),
        ]);

        $module->fields()->create([
            'name' =>  'user_to',
            'type' => 30,
            'position' => 100,
            'label' => 'Usuario Destino',
        ]);

        //user to store
        $module->fields()->create([
            'name' =>  'user_to_store_enable',
            'type' => 18,
            'position' => 1,
            'label' => 'Cambiar de Usuario a Almacen',
            'depend' => 1,
            'inputs_depend' => json_encode([
                "user_from" => [
                    "field" => "user_from",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Usuario Origen",
                    "placeholder" => "Seleccione un Usuario",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "search" => [
                        "model" => "App\Models\User",
                        "id" => "id",
                        "text" => "name",
                        "scope" => "notClientRole",
                    ]
                ],
                "store_to" => [
                    "field" => "store_to",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Almacen Destino",
                    "placeholder" => "Seleccione un Almacen",
                    "position" => 2,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "search" => [
                        "model" => "App\Models\InventoryStore",
                        "id" => "id",
                        "text" => "name",
                    ]
                ],
            ]),
        ]);

        $module->fields()->create([
            'name' =>  'user_from',
            'type' => 30,
            'position' => 100,
            'label' => 'Usuario Origen',
        ]);

        //user to client
        $module->fields()->create([
            'name' =>  'user_to_client_enable',
            'type' => 18,
            'position' => 1,
            'label' => 'Cambiar de Usuario a Cliente',
            'depend' => 1,
            'inputs_depend' => json_encode([
                "user_from" => [
                    "field" => "user_from",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Usuario Origen",
                    "placeholder" => "Seleccione un Usuario",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "search" => [
                        "model" => "App\Models\User",
                        "id" => "id",
                        "text" => "name",
                        "scope" => "notClientRole",
                    ]
                ],
                "client_to" => [
                    "field" => "client_to",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Cliente Destino",
                    "placeholder" => "Seleccione un Cliente",
                    "position" => 2,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "search" => [
                        "model" => "App\Models\ClientMainInformation",
                        "id" => "client_id",
                        "text" => "name",
                    ]
                ],
            ]),
        ]);

        $module->fields()->create([
            'name' =>  'client_to',
            'type' => 30,
            'position' => 100,
            'label' => 'Cliente Destino',
        ]);
        $module->fields()->create([
            'name' =>  'id_item',
            'type' => 30,
            'position' => 100,
            'label' => 'Id Item',
        ]);
        $module->fields()->create([
            'name' =>  'quantity',
            'type' => 15,
            'position' => 2,
            'label' => 'Cantidad',
            'options' => json_encode([
                'min' => 1
            ]),
            'default_value' => 1
        ]);

        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [14];
        $ckeditor = [23];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor]);
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'InventoryMovementAll')->first();
        $module->packages()->detach();
        $module->fields()->delete();
        $module->delete();
    }
};
