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
        $module = Module::where('name', 'InventoryItem')->first();
        $module->columnsDatatable()->create([
            'name' => 'zone',
            'label' => 'Zona',
            'order' => 9
        ]);
        $module = Module::where('name', 'InventoryItemStock')->first();
        $module->columnsDatatable()->create([
            'name' => 'zone',
            'label' => 'Zona',
            'order' => 9
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'InventoryItem')->first();
        $module->columnsDatatable()->where('name','zone')->delete();
        $module = Module::where('name', 'InventoryItemStock')->first();
        $module->columnsDatatable()->where('name','zone')->delete();
    }
};
