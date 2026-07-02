<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 6 — Columna dedicada fecha_pago en whatsapp_payment_extractions.
 *
 * La fecha en que se REALIZÓ el pago (del comprobante) ya viaja dentro del blob
 * fields (json), pero se expone como columna top-level — igual que 'concepto' —
 * para mostrarla/consultarla fácil en la pantalla de extracción y en la cola.
 * Aditiva, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('whatsapp_payment_extractions', 'fecha_pago')) {
            return;
        }
        Schema::table('whatsapp_payment_extractions', function (Blueprint $table) {
            $table->string('fecha_pago')->nullable()->after('concepto');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_payment_extractions', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_payment_extractions', 'fecha_pago')) {
                $table->dropColumn('fecha_pago');
            }
        });
    }
};
