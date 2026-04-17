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
            'name' => 'fecha_pago',
            'label' => 'Proximo Pago',
            'filter_name' => 'clients.fecha_pago',
            'order' => 95,
        ]);
        $module->columnsDatatable()->create([
            'name' => 'fecha_fin_periodo_gracia',
            'label' => 'Fecha Fin de Periodo de Gracia',
            'filter_name' => 'clients.fecha_fin_periodo_gracia',
            'order' => 96,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Client')->first();
        $module->columnsDatatable()->where('name','fecha_pago')->delete();
        $module->columnsDatatable()->where('name','fecha_fin_periodo_gracia')->delete();
    }
};
