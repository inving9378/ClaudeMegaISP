<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Siembra el motor de cobro NATIVO como proveedor default y no enrutable.
     * provider='native_reference' es la llave estable; OpenPay queda secundario
     * (is_default=false). Idempotente: updateOrInsert por la llave `provider`.
     *
     * No se usa el cast 'encrypted:json' del modelo aquí (config nula) para no
     * depender de APP_KEY durante la migración.
     */
    public function up(): void
    {
        $now = now();

        DB::table('payment_providers')->updateOrInsert(
            ['provider' => 'native_reference'],
            [
                'name'        => 'Conciliación Meganet',
                'is_active'   => 1,
                'is_default'  => 1,
                'is_routable' => 0,
                'updated_at'  => $now,
                'created_at'  => $now,
            ]
        );

        // Cualquier otro proveedor (OpenPay, etc.) deja de ser default.
        DB::table('payment_providers')
            ->where('provider', '!=', 'native_reference')
            ->update(['is_default' => 0]);
    }

    public function down(): void
    {
        DB::table('payment_providers')->where('provider', 'native_reference')->delete();
    }
};
