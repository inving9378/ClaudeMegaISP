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
        $module = \App\Models\Module::where('name','Bundle')->first();
        $module->columnsDatatable()->where('name','action')->update(['order' => 9999]);
        $module->columnsDatatable()->create([
            'name' =>'instalation_cost',
            'label' => 'Costo de Instalación',
            'order' => 18
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = \App\Models\Module::where('name','Bundle')->first();
        $module->columnsDatatable()->where('name','instalation_cost')->delete();
    }
};
