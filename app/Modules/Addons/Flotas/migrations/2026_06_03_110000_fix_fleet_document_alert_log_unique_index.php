<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: el índice único de 3 cols (document_id, threshold, channel) colapsaba
 * todos los envíos a distintos destinatarios en un solo registro. El dedup debe
 * ser por destinatario para que cada usuario reciba su alerta exactamente una vez,
 * y el audit trail sea completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_document_alert_log', function (Blueprint $table) {
            // Agregar índice regular ANTES de soltar el único, para que MySQL no
            // rechace el drop por ser el único soporte del FK en document_id.
            $table->index('document_id', 'idx_doc_alert_document_id');
        });

        Schema::table('fleet_document_alert_log', function (Blueprint $table) {
            $table->dropUnique('uniq_doc_threshold_channel');
            // Dedup: un destinatario recibe cada alerta (doc+umbral+canal) a lo más una vez.
            $table->unique(['document_id', 'threshold', 'channel', 'recipient'], 'uniq_doc_threshold_channel_recipient');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_document_alert_log', function (Blueprint $table) {
            $table->dropUnique('uniq_doc_threshold_channel_recipient');
            $table->unique(['document_id', 'threshold', 'channel'], 'uniq_doc_threshold_channel');
        });

        Schema::table('fleet_document_alert_log', function (Blueprint $table) {
            $table->dropIndex('idx_doc_alert_document_id');
        });
    }
};
