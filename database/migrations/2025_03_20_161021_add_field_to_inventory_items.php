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
        $module->fields()->create([
            'name' => 'image',
            'label' => 'Imagen',
            'placeholder' => 'Agregar Imagen',
            'type' => 44,
            'additional_field'=> false,
            'position' => 800
        ]);

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('image')->nullable()->after('status_item');
            $table->string('url_image')->nullable()->after('image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'InventoryItem')->first();
        $module->fields()->where('name', 'image')->first()->delete();
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('image');
            $table->dropColumn('url_image');
        });
    }
};
