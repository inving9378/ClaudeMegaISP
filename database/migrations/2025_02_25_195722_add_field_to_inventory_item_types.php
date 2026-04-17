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
        Schema::table('inventory_item_types', function (Blueprint $table) {
            $table->enum('type', ['tool', 'material'])->default('tool')->after('created_by');
        });

        $module = Module::where('name', 'InventoryItemType')->first();
        $module->fields()->create([
            'name' => 'type',
            'label' => 'Tipo',
            'type' => 22,
            'placeholder' => 'Seleccione',
            'position' => 3,
            'options' => json_encode([
                'tool' => 'Herramienta',
                'material' => 'Material'
            ])
        ]);
        $module->columnsDatatable()->create([
            'name' => 'type',
            'label' => 'Tipo',
            'order' => 4
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_item_types', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        $module = Module::where('name', 'InventoryItemType')->first();
        $module->fields()->where('name', 'type')->first()->delete();
        $module->columnsDatatable()->where('name', 'type')->first()->delete();
    }
};
