<?php

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
        $module = \App\Models\Module::where('name','ClientInternetService')->first();
        $module->fields()->create([
            'name' =>  'cost_instalation',
            'label' => 'Costo de Instalación',
            'type' => 15,
            'placeholder' => '0.00',
            'position' => 29,
            'disabled' => 1,
            'partition' => 'other',
            'depend' =>'depend-checkbox',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = \App\Models\Module::where('name','ClientInternetService')->first();
        $module->fields()->where('name','cost_instalation')->delete();
    }
};
