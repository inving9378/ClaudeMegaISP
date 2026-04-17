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
        $module = Module::where('name', Module::CLIENT_MODULE_NAME)->first();
        $module->columnsDatatable()->where('name', 'billing_date')->first()->delete();
        $module->columnsDatatable()->where('name', 'billing_date')
            ->first()->update(['label' => "Día de facturación"]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', Module::CLIENT_MODULE_NAME)->first();
        $module->columnsDatatable()->create([
            'name' => 'billing_date',
            'label' => 'Fecha de Facturacion',
            'order' => 92,
            'active' => true,
            'filter_name' => 'billing_configurations.billing_date'
        ]);
    }
};
