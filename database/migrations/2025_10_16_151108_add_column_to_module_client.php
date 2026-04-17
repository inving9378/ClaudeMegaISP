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
        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable()->create([
            'name' => 'box_nomenclator_old',
            'label' => 'Nomenclatura Antigua',
            'filter_name' => 'client_additional_information.box_nomenclator_old',
            'order' => 95
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable()->where('name', 'box_nomenclator_old')->delete();
    }
};
