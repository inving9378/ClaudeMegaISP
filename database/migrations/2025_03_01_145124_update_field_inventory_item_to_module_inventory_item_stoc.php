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
        $module = Module::where('name', 'InventoryMovementAll')->first();
        $module->fields()->where('name', 'id_item')
            ->update([
                'search' => json_encode([
                    'model' => 'App\Models\InventoryItem',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'hasStock'
                ])
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'InventoryMovementAll')->first();
        $module->fields()->where('name', 'id_item')
            ->update([
                'search' => json_encode([
                    'model' => 'App\Models\InventoryItem',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ]);
    }
};
