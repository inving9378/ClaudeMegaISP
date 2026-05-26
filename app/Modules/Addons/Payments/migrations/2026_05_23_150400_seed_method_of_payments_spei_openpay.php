<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Inserta fila "SPEI OpenPay" en method_of_payments para que payments
     * pueda referenciarla vía payment_method_id (catálogo existente con
     * valores como "Efectivo en Caja", "Transferencia Bancaria", etc.).
     *
     * Idempotente: solo inserta si no existe ya.
     */
    public function up(): void
    {
        $exists = DB::table('method_of_payments')->where('type', 'SPEI OpenPay')->exists();
        if (!$exists) {
            DB::table('method_of_payments')->insert([
                'type'   => 'SPEI OpenPay',
                'active' => 1,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('method_of_payments')->where('type', 'SPEI OpenPay')->delete();
    }
};
