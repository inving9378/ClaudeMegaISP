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
        $module = Module::where('name', 'ClientPayment')->first();
         $module->fields()->where('name', 'payment_period')
            ->update([
                'default_value' => json_encode([
                    'request' => '/get-payment-period'
                ]),
                'type' => 3,
                'options' => null
            ]);
        // $module->fields()->where('name', 'payment_period')
        //     ->update([
        //         'default_value' => null,
        //         'type' => 22,
        //         'options' => json_encode([
        //             '01' => 'Enero-Febrero',
        //             '02' => 'Febrero -Marzo',
        //             '03' => 'Marzo-Abril',
        //             '04' => 'Abril-Mayo',
        //             '05' => 'Mayo-Junio',
        //             '06' => 'Junio-Julio',
        //             '07' => 'Julio-Agosto',
        //             '08' => 'Agosto-Septiembre',
        //             '09' => 'Septiembre-Octubre',
        //             '10' => 'Octubre-Noviembre',
        //             '11' => 'Noviembre-Diciembre',
        //             '12' => 'Diciembre-Enero',
        //         ])
        //     ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'ClientPayment')->first();
        $module->fields()->where('name', 'payment_period')
            ->update([
                'default_value' => json_encode([
                    'request' => '/get-payment-period'
                ]),
                'type' => 1,
                'options' => null
            ]);
    }
};
