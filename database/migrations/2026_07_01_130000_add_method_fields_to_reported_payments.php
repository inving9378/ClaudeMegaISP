<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE PAGOS 2b — Campos propios de método en la captura manual (modal
 * "Crear Gasto"). Aditiva: clave_rastreo / titular / banco_origen /
 * comprobante_path ya existían (Paso 2). Aquí solo faltan:
 *  - referencia_oxxo: folio/referencia de venta Oxxo.
 *  - tecnico_id: técnico receptor del pago (método "Pago a técnico").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reported_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('reported_payments', 'referencia_oxxo')) {
                $table->string('referencia_oxxo')->nullable()->after('banco_origen');
            }
            if (!Schema::hasColumn('reported_payments', 'tecnico_id')) {
                $table->unsignedBigInteger('tecnico_id')->nullable()->after('referencia_oxxo');
                $table->foreign('tecnico_id')->references('id')->on('users')->nullOnDelete();
                $table->index('tecnico_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reported_payments', function (Blueprint $table) {
            if (Schema::hasColumn('reported_payments', 'tecnico_id')) {
                $table->dropForeign(['tecnico_id']);
                $table->dropIndex(['tecnico_id']);
                $table->dropColumn('tecnico_id');
            }
            if (Schema::hasColumn('reported_payments', 'referencia_oxxo')) {
                $table->dropColumn('referencia_oxxo');
            }
        });
    }
};
