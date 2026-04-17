<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('method_of_payments')->truncate();
        $inputs = [
            [
                'id' => 1,
                'type' => 'Efectivo en Caja',
                'active' => 1
            ],
            [
                'id' => 2,
                'type' => 'Transferencia Bancaria',
                'active' => 1
            ],
            [
                'id' => 3,
                'type' => 'Tarjeta de Credito o debito en Oficina',
                'active' => 1
            ],
            [
                'id' => 4,
                'type' => 'PayPal',
                'active' => 1
            ],
            [
                'id' => 5,
                'type' => 'Oxxo',
                'active' => 1
            ],
            [
                'id' => 6,
                'type' => 'TARJETAS PREPAGO',
                'active' => 1
            ],
            [
                'id' => 7,
                'type' => 'PAGO A TECNICO',
                'active' => 1
            ],
        ];

        foreach ($inputs as $input) {
            DB::table('method_of_payments')->insert($input);
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
