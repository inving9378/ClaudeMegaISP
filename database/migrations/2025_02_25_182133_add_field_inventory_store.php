<?php

use App\Models\InventoryStore;
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
        $module = Module::where('name', 'InventoryStore')->first();
        $module->fields()->create([
            'name' => 'user_id',
            'label' => 'Responsable de Almacén',
            'type' => 22,
            'placeholder' => 'Seleccione',
            'position' => 3,
            'search' => json_encode([
                'model' => 'App\Models\User',
                'id' => 'id',
                'text' => 'name',
                'scope'=>'notClientRole'
            ])
        ]);
        $module->columnsDatatable()->create([
            'name' => 'user_id',
            'label' => 'Responsable de Almacén',
            'order' => 4
        ]);
        Schema::table('inventory_stores', function (Blueprint $table) {
            $table->string('user_id')->nullable();
        });

        $inventoryStores = InventoryStore::all();
        foreach ($inventoryStores as $inventoryStore) {
            $inventoryStore->user_id = 1;
            $inventoryStore->save();
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'InventoryStore')->first();
        $module->fields()->where('name', 'user_id')->first()->delete();
        $module->columnsDatatable()->where('name', 'user_id')->first()->delete();
        Schema::table('inventory_stores', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
