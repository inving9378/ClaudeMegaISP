<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->boolean('serial_number_enable')->default(0)->after('created_by');
            $table->string('serial_number')->nullable()->after('serial_number_enable');
            $table->boolean('status_item_enable')->default(0)->after('serial_number');
            $table->enum('status_item', ['new', 'used', 'repair', 'warranty', 'broken'])->default('new')->after('status_item_enable');
        });

        $module = \App\Models\Module::where('name', 'InventoryItem')->first();
        $module->fields()->create([
            'name' =>  'serial_number_enable',
            'type' => 18,
            'position' => 20,
            'label' => 'Activar Numero de Serie',
            'depend' => 1,
            'inputs_depend' => json_encode([
                "serial_number" => [
                    "field" => "serial_number",
                    "type" => "input-string",
                    "value" => null,
                    "label" => "Numero de Serie",
                    "placeholder" => "Numero de Serie",
                    "position" => 1,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                ]
            ]),
        ]);

        $module->fields()->create([
            'name' =>  'serial_number',
            'type' => 30,
            'position' => 100,
            'label' => 'Numero de Serie',
        ]);

        $module->fields()->create([
            'name' =>  'status_item_enable',
            'type' => 18,
            'position' => 21,
            'label' => 'Estado del Articulo',
            'depend' => 1,
            'inputs_depend' => json_encode([
                "status_item" => [
                    "field" => "status_item",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Estado del Articulo",
                    "placeholder" => "Seleccione Estado",
                    "position" => 2,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    'options' => [
                        'new' => 'Nuevo',
                        'used' => 'Usado',
                        'repair' => 'En Reparacion',
                        'warranty' => 'Garantia',
                        'broken' => 'Roto'
                    ]
                ]
            ]),
        ]);

        $module->fields()->create([
            'name' =>  'status_item',
            'type' => 30,
            'position' => 100,
            'label' => 'Estado del Articulo',
        ]);


        $module->columnsDatatable()->create([
            'name' => 'status_item',
            'label' => 'Estado del Articulo',
            'order' => 7
        ]);

        $module->columnsDatatable()->create([
            'name' => 'serial_number',
            'label' => 'Numero de Serie',
            'order' => 8
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('serial_number_enable');
            $table->dropColumn('serial_number');
            $table->dropColumn('status_item_enable');
            $table->dropColumn('status_item');
        });

        $module = \App\Models\Module::where('name', 'InventoryItem')->first();
        $module->fields()->where('name', 'serial_number_enable')->delete();
        $module->fields()->where('name', 'serial_number')->delete();
        $module->fields()->where('name', 'status_item_enable')->delete();
        $module->fields()->where('name', 'status_item')->delete();
        $module->columnsDatatable()->where('name', 'serial_number')->delete();
        $module->columnsDatatable()->where('name', 'status_item')->delete();
    }
};
