<?php

use App\Models\Module;
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
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->boolean('user_id_enable')->default(0)->after('created_by');
            $table->string('user_id')->nullable()->after('user_id_enable');
            $table->boolean('inventory_store_id_enable')->default(0)->after('user_id');
            $table->string('inventory_store_id')->nullable()->after('inventory_store_id_enable');
        });
        $module = Module::where('name', 'InventoryMovement')->first();
        $module->fields()->create([
            'name' =>  'user_id_enable',
            'type' => 18,
            'position' => 21,
            'label' => 'Asignar a un Usuario',
            'depend' => 1,
            'inputs_depend' => json_encode([
                "user_id" => [
                    "field" => "user_id",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Asignar a usuario",
                    "placeholder" => "Seleccione",
                    "position" => 2,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "search" => [
                        'model' => 'App\Models\User',
                        'id' => 'id',
                        'text' => 'name',
                        'scope' => 'notClientRole',
                    ]
                ]
            ]),
        ]);

        $module->fields()->create([
            'name' =>  'user_id',
            'type' => 30,
            'position' => 100,
            'label' => 'Usuario',
        ]);

        $module->fields()->create([
            'name' =>  'inventory_store_id_enable',
            'type' => 18,
            'position' => 21,
            'label' => 'Mover a Almacén',
            'depend' => 1,
            'inputs_depend' => json_encode([
                "inventory_store_id" => [
                    "field" => "inventory_store_id",
                    "type" => "select-component",
                    "value" => null,
                    "label" => "Almacén",
                    "placeholder" => "Seleccione el Almacén",
                    "position" => 2,
                    "class_label" => "col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center",
                    "class_field" => "col-sm-12 col-md-9",
                    "class_col" => "full",
                    "search" => [
                        'model' => 'App\Models\InventoryStore',
                        'id' => 'id',
                        'text' => 'name',
                    ]
                ]
            ]),
        ]);

        $module->fields()->create([
            'name' =>  'inventory_store_id',
            'type' => 30,
            'position' => 100,
            'label' => 'Almacen',
        ]);


        $module->columnsDatatable()->create([
            'name' => 'user_id',
            'label' => 'Asignado a Usuario',
            'order' => 10
        ]);

        $module->columnsDatatable()->create([
            'name' => 'inventory_store_id',
            'label' => 'Asignado a Almacén',
            'order' => 11
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'InventoryMovement')->first();
        $module->fields()->where('name', 'user_id_enable')->delete();
        $module->fields()->where('name', 'user_id')->delete();
        $module->fields()->where('name', 'inventory_store_id_enable')->delete();
        $module->fields()->where('name', 'inventory_store_id')->delete();

        $module->columnsDatatable()->where('name', 'user_id')->delete();
        $module->columnsDatatable()->where('name', 'inventory_store_id')->delete();

        Schema::table('inventory_movements', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_movements', 'user_id_enable')) {
                $table->dropColumn('user_id_enable');
            }
            if (Schema::hasColumn('inventory_movements', 'user_id')) {
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('inventory_movements', 'inventory_store_id_enable')) {
                $table->dropColumn('inventory_store_id_enable');
            }
            if (Schema::hasColumn('inventory_movements', 'inventory_store_id')) {
                $table->dropColumn('inventory_store_id');
            }
        });
    }
};
