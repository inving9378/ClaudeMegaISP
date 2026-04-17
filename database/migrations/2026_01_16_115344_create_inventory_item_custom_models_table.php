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
        Schema::create('inventory_item_custom_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('inventory_item_type_id')->constrained();
            $table->timestamps();
            $table->softDeletes();
        });

        $module = Module::create([
            'name' => 'InventoryItemCustomModel',
            'group' => 'Administration'
        ]);
        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $ckeditor = [23];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor]);
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);

        $fields = [
            [
                'name' => 'name',
                'label' => 'Nombre',
                'placeholder' => '',
                'hint' => 'Este nombre es el que generaliza los articulos',
                'type' => 1,
                'additional_field' => false,
                'position' => 1,
            ],
            [
                'name' => 'inventory_item_type_id',
                'label' => 'Tipo de Articulo',
                'placeholder' => 'Tipo',
                'type' => 43,
                'position' => 2,
                'search' => json_encode([
                    "model" => "App\\Models\\InventoryItemType",
                    "id" => "id",
                    "text" => "name",
                ]),
                'additional_field' => false,
            ]
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
                'name' => 'inventory_item_type_id',
                'label' => 'Tipo de Articulo',
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_item_custom_models');
        $module = Module::where('name','InventoryItemCustomModel')->first();
        $module->fields()->delete();
        $module->columnsDatatable()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
