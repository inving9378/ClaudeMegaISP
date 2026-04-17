<?php

use App\Models\InventoryMovement;
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
        $inventory_movements = InventoryMovement::where('description', 'Ingreso Inicial')->get();
        foreach ($inventory_movements as $inventory_movement) {
            $inventory_movement->movementable_from_id = 1;
            $inventory_movement->movementable_from_type = 'App\Models\User';
            $inventory_movement->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $inventory_movements = InventoryMovement::where('description', 'Ingreso Inicial')->get();
        foreach ($inventory_movements as $inventory_movement) {
            $inventory_movement->movementable_from_id = 0;
            $inventory_movement->movementable_from_type = 'System';
            $inventory_movement->save();
        }
    }
};
