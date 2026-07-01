<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuentas receptoras (portal_pago_accounts): se agregan el número de
     * cuenta bancaria y la tarjeta para que Irving registre su cuenta real
     * (Santander) con todos los datos por UI. Aditiva, nullable: no afecta
     * las filas/flujos existentes (la CLABE sigue siendo el dato obligatorio).
     */
    public function up(): void
    {
        Schema::table('portal_pago_accounts', function (Blueprint $table) {
            $table->string('cuenta', 20)->nullable()->after('clabe');
            $table->string('tarjeta', 20)->nullable()->after('cuenta');
        });
    }

    public function down(): void
    {
        Schema::table('portal_pago_accounts', function (Blueprint $table) {
            $table->dropColumn(['cuenta', 'tarjeta']);
        });
    }
};
