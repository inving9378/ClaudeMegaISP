<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = Module::where('name', 'InventoryMovementAll')->first();
        $module->fields()->delete();

        $module->fields()->create([
            'name' =>  'id_item',
            'type' => 22,
            'position' => 1,
            'label' => 'Selccione Articulo',
            'search' => json_encode([
                'model' => 'App\Models\InventoryItem',
                'id' => 'id',
                'text' => 'name',
            ]),
            "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
            "class_field" => "col-sm-12 col-md-9",
            "class_col" => "full",
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


        $module->fields()->create([
            'name' =>  'select_from',
            'type' => 22,
            'position' => 3,
            'label' => 'Selccione Origen',
            'options' => json_encode([
                'store' => 'Almacén',
                'user' => 'Usuario',
                'client' => 'Cliente',
            ]),
            'class_col' => 'partial',
            'class_label' => 'col-form-label pr-2',
            'class_field' => 'col-12',

        ]);
        $module->fields()->create([
            'name' =>  'select_to',
            'type' => 22,
            'position' => 4,
            'label' => 'Seleccione Destino',
            'options' => json_encode([
                'store' => 'Almacén',
                'user' => 'Usuario',
                'client' => 'Cliente',
            ]),

            'class_col' => 'partial',
            'class_label' => 'col-form-label pr-2',
            'class_field' => 'col-12',
        ]);

        $module->fields()->create([
            'name' =>  'user_to_user_enable',
            'type' => 30,
            'position' => 100,
            'label' => 'user_to_user_enable',
        ]);

        $module->fields()->create([
            'name' =>  'user_to_client_enable',
            'type' => 30,
            'position' => 100,
            'label' => 'user_to_client_enable',
        ]);

        $module->fields()->create([
            'name' =>  'user_to_store_enable',
            'type' => 30,
            'position' => 100,
            'label' => 'user_to_store_enable',
        ]);

        $module->fields()->create([
            'name' =>  'store_to_store_enable',
            'type' => 30,
            'position' => 100,
            'label' => 'store_to_store_enable',
        ]);

        $module->fields()->create([
            'name' =>  'store_to_user_enable',
            'type' => 30,
            'position' => 100,
            'label' => 'store_to_user_enable',
        ]);

        $module->fields()->create([
            'name' =>  'store_to_client_enable',
            'type' => 30,
            'position' => 100,
            'label' => 'store_to_client_enable',
        ]);

        $module->fields()->create([
            'name' =>  'client_to_client_enable',
            'type' => 30,
            'position' => 100,
            'label' => 'client_to_client_enable',
        ]);

        $module->fields()->create([
            'name' =>  'client_to_user_enable',
            'type' => 30,
            'position' => 100,
            'label' => 'client_to_user_enable',
        ]);

        $module->fields()->create([
            'name' =>  'client_to_store_enable',
            'type' => 30,
            'position' => 100,
            'label' => 'client_to_store_enable',
        ]);

        $module->fields()->create([
            'name' =>  'store_from',
            'type' => 30,
            'position' => 100,
            'label' => 'store_from',
        ]);
        $module->fields()->create([
            'name' =>  'store_to',
            'type' => 30,
            'position' => 100,
            'label' => 'store_to',
        ]);
        $module->fields()->create([
            'name' =>  'user_from',
            'type' => 30,
            'position' => 100,
            'label' => 'user_from',
        ]);
        $module->fields()->create([
            'name' =>  'user_to',
            'type' => 30,
            'position' => 100,
            'label' => 'user_to',
        ]);
        $module->fields()->create([
            'name' =>  'client_from',
            'type' => 30,
            'position' => 100,
            'label' => 'client_from',
        ]);
        $module->fields()->create([
            'name' =>  'client_to',
            'type' => 30,
            'position' => 100,
            'label' => 'client_to',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        $module = Module::where('name', 'InventoryMovementAll')->first();
        $module->fields()->delete();
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
    }
};
