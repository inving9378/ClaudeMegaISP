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
        $module->columnsDatatable()->create([
            'name' => 'billing_date',
            'label' => 'Fecha de Facturacion',
            'order' => 92,
            'active' => true,
            'filter_name' => 'billing_configurations.billing_date'
        ]);

        $module->columnsDatatable()->create([
            'name' => 'last_payment',
            'label' => 'Fecha de Ultimo Pago',
            'order' => 93,
            'active' => true,
            'filter_name' => null
        ]);


        $columns = [
            94 => "action",
        ];
        foreach ($columns as $order => $value) {
            $module->columnsDatatable()->where('name', $value)->update([
                'order' => $order
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', Module::CLIENT_MODULE_NAME)->first();
        $module->columnsDatatable()->where('name', 'billing_date')->delete();
        $module->columnsDatatable()->where('name', 'last_payment')->delete();
    }
};
