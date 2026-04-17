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
        $module = Module::where('name', 'InventoryItemStock')->first();
        $module->columnsDatatable()->create([
            'name' => 'reserved_stock',
            'label' => 'En Reserva',
            'order' => 5
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'InventoryItemStock')->first();
        $module->columnsDatatable()->where('name', 'reserved_stock')->delete();
    }
};
