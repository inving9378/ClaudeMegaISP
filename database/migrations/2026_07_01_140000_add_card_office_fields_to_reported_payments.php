<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE PAGOS 2c — Método "Tarjeta de crédito/débito en oficina" (id=3, cobro
 * con terminal). Aditiva:
 *  - numero_autorizacion: autorización de la terminal/voucher.
 *  - ultimos4_tarjeta: SOLO últimos 4 dígitos (PCI — jamás el PAN completo).
 *    Columna de largo 4 como barrera física adicional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reported_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('reported_payments', 'numero_autorizacion')) {
                $table->string('numero_autorizacion')->nullable()->after('referencia_oxxo');
            }
            if (!Schema::hasColumn('reported_payments', 'ultimos4_tarjeta')) {
                $table->string('ultimos4_tarjeta', 4)->nullable()->after('numero_autorizacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reported_payments', function (Blueprint $table) {
            if (Schema::hasColumn('reported_payments', 'ultimos4_tarjeta')) {
                $table->dropColumn('ultimos4_tarjeta');
            }
            if (Schema::hasColumn('reported_payments', 'numero_autorizacion')) {
                $table->dropColumn('numero_autorizacion');
            }
        });
    }
};
