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
        $module = \App\Models\Module::where('name', 'BundleRight')->first();
        $module->fields()->where('name', 'discount_period')->update([
            'type' => 22,
            'options' => json_encode([
                '1' => '1 mes',
                '2' => '2 meses',
                '3' => '3 meses',
                '4' => '4 meses',
                '5' => '5 meses',
                '6' => '6 meses',
                '7' => '7 meses',
                '8' => '8 meses',
                '9' => '9 meses',
                '10' => '10 meses',
                '11' => '11 meses',
                '12' => '12 meses',
                '16' => '1 año y 4 meses',
                '18' => '1 año y 6 meses',
                '24' => '2 años',
            ]),
            'placeholder' => 'Seleccione un periodo de descuento'
        ]);

        $module->fields()->create([
            'name' => 'discount_value_fixed',
            'label' => 'Tasa fija de Descuento',
            'type' => 15,
            'placeholder' => '0.00',
            'position' => 11,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {

        $module = \App\Models\Module::where('name', 'BundleRight')->first();
        $module->fields()->where('name', 'discount_period')->update([
            'type' => 15,
            'options' => null,
            'placeholder' => '0.00',
        ]);

        $module->fields()->where('name', 'discount_value_fixed')->delete();
    }
};
