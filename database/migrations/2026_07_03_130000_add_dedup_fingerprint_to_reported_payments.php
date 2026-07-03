<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Huella anti-duplicado alternativa para comprobantes SIN clave de rastreo.
 *
 * No todos los comprobantes traen clave de rastreo (efectivo, algunos Mercado
 * Pago, etc.). Cuando falta la clave, se genera una huella (monto + fecha/hora
 * + banco origen) para seguir detectando duplicados. Prioridad: si hay clave se
 * usa la clave (más fuerte); si no, la huella. Aditivo: 1 columna nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reported_payments', function (Blueprint $table) {
            $table->string('dedup_fingerprint')->nullable()->index()->after('clave_rastreo');
        });
    }

    public function down(): void
    {
        Schema::table('reported_payments', function (Blueprint $table) {
            $table->dropColumn('dedup_fingerprint');
        });
    }
};
