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
        try {
            $module = Module::where('name', 'InventoryItem')->first();
            $module->fields()->create([
                'name' => 'inventory_store_id',
                'label' => 'Almacén',
                'placeholder' => 'Seleccione un almacén',
                'type' => 22,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\InventoryStore',
                    'id' => 'id',
                    'text' => 'name',
                ]),
                'position' => 5
            ]);
            $module->columnsDatatable()->create([
                'name' => 'inventory_store_id',
                'label' => 'Almacén',
                'order' => 6
            ]);
        } catch (\Throwable $th) {

        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        $module = Module::where('name', 'InventoryItem')->first();
        $module->fields()->where('name', 'inventory_store_id')->first()->delete();
        $module->columnsDatatable()->where('name', 'inventory_store_id')->first()->delete();
    }
};
