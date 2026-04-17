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
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('high_limit')->after('status_item')->default(2);
            $table->string('middle_limit')->after('high_limit')->default(1);
        });


        $module = Module::where('name', 'InventoryItem')->first();
        $module->fields()->create([
            'name' => 'high_limit',
            'label' => 'Límite Alto',
            'type' => 15,
            'position' => 22,
            'additional_field' => false,
            'options' => json_encode([
                'min' => 2
            ]),
            'default_value' => 2,
            'hint' => 'Por debajo de este valor se considera medio'
        ]);

        $module->fields()->create([
            'name' => 'middle_limit',
            'label' => 'Límite Medio',
            'type' => 15,
            'position' => 23,
            'additional_field' => false,
            'options' => json_encode([
                'min' => 1
            ]),
            'default_value' => 1,
            'hint' => 'Por debajo de este valor se considera bajo'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('high_limit');
            $table->dropColumn('middle_limit');
        });
        $module = Module::where('name', 'InventoryItem')->first();
        $module->fields()->where('name', 'high_limit')->delete();
        $module->fields()->where('name', 'middle_limit')->delete();
    }
};
