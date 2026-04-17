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
        $module = \App\Models\Module::where('name','ClientBundleService')->first();
        $module->fields()->create([
            'name' =>  'bundle_cost_instalation',
            'label' => 'Costo de Instalación',
            'type' => 15,
            'placeholder' => '0.00',
            'position' => 24,
            'disabled' => 1,
            'partition' => 'bundle_service_option'
        ]);
        $module->columnsDatatable()->create([
            'name' =>'cost_instalation',
            'label' => 'Costo de Instalación',
            'order' => 6
        ]);
        $module->columnsDatatable()->where('name','action')->update(['order' => 9999]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = \App\Models\Module::where('name','ClientBundleService')->first();
        $module->fields()->where('name','bundle_cost_instalation')->delete();
        $module->columnsDatatable()->where('name','cost_instalation')->delete();
    }
};
