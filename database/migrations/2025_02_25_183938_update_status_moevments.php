<?php

use App\Models\InventoryMovement;
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
        $module = Module::where('name', 'InventoryMovement')->first();
        $module->columnsDatatable()->where('name', 'user_id')->delete();
        $module->columnsDatatable()->where('name', 'inventory_store_id')->delete();
        $module->columnsDatatable()->create([
            'name' => 'from',
            'label' => 'Desde',
            'order' => 10
        ]);
        $module->columnsDatatable()->create([
            'name' => 'to',
            'label' => 'Hacia',
            'order' => 11
        ]);
        $module->columnsDatatable()->create([
            'name' => 'created_by',
            'label' => 'Realizado por',
            'order' => 12
        ]);
        $module->columnsDatatable()->create([
            'name' => 'status',
            'label' => 'Estado',
            'order' => 13
        ]);

        $inventory_movements = InventoryMovement::where('status', 'pending')->get();
        foreach ($inventory_movements as $inventory_movement) {
            $inventory_movement->status = 'accepted';
            $inventory_movement->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'InventoryMovement')->first();
        $module->columnsDatatable()->where('name', 'from')->delete();
        $module->columnsDatatable()->where('name', 'to')->delete();
        $module->columnsDatatable()->where('name', 'created_by')->delete();
        $module->columnsDatatable()->where('name', 'status')->delete();

        $inventory_movements = InventoryMovement::where('status', 'accepted')->get();
        foreach ($inventory_movements as $inventory_movement) {
            $inventory_movement->status = 'pending';
            $inventory_movement->save();
        }
    }
};
