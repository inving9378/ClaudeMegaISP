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
        $module = Module::where('name', 'ConfigFinanceNotificationProformaInvoice')->first();
        $arrayHasta31 = [];
        for ($i = 1; $i <= 31; $i++) {
            $arrayHasta31[$i] = $i;
        }

        $module->fields()->create([
            'name' => 'delay_days',
            'label' => 'Dias antelacion',
            'placeholder' => 'Seleccione Día',
            'type' => 22,
            'position' => 15,
            'additional_field' => false,
            'options' => json_encode($arrayHasta31)
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'ConfigFinanceNotificationProformaInvoice')->first();
        $module->fields()->where('name', 'delay_days')->delete();
    }
};
