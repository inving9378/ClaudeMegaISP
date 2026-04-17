<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function PHPSTORM_META\type;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = Module::where('name', 'InventoryItem')->first();
        $module->fields()->where('name', 'inventory_store_id')->first()->update([
            'type' => 3,
        ]);

        $module->fields()->create([
            'name' => 'store_zone_id',
            'type' => 3,
        ]);
        //
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'InventoryItem')->first();
        $module->fields()->where('name', 'inventory_store_id')->first()->update([
            'type' => 22,
        ]);
        $module->fields()->where('name', 'store_zone_id')->first()->delete();
    }
};
