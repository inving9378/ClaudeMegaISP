<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 2 — Columna dedicada 'concepto' en whatsapp_payment_extractions.
 *
 * El texto de concepto/referencia que escribe el pagador ya viaja dentro del
 * blob 'fields' (json), pero se expone también como columna top-level porque
 * la Fase 3 buscará ahí la referencia MEG-XXXXXXXX-XX del cliente (indexable,
 * mucho mejor que consultar dentro del JSON).
 *
 * Aditiva y nullable — no rompe nada existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('whatsapp_payment_extractions', 'concepto')) {
            return;
        }

        Schema::table('whatsapp_payment_extractions', function (Blueprint $table) {
            $table->string('concepto')->nullable()->after('source_mime');
            $table->index('concepto');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_payment_extractions', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_payment_extractions', 'concepto')) {
                $table->dropIndex(['concepto']);
                $table->dropColumn('concepto');
            }
        });
    }
};
