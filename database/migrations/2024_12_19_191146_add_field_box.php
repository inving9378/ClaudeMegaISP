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
        $module = \App\Models\Module::where('name','ClientAdditionalInformation')->first();
        $module->fields()->create([
            'name' =>  'box_nomenclator_old',
            'label' => 'Nomenclatura antigua',
            'type' => 30,
            'placeholder' => '0.00',
            'position' => 29,
            'disabled' => 1,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = \App\Models\Module::where('name','ClientAdditionalInformation')->first();
        $module->fields()->where('name','box_nomenclator_old')->delete();
    }
};
