<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aditiva idempotente: discriminador de FUENTE en las tablas de conciliación,
 * para que puedan recibir comprobantes que entran por el GATEWAY (WhatsAppAgent)
 * SIN colisionar con los de Marketing (meganet-ventas), que quedan como default.
 *
 *   source                = 'marketing' (default, ruta viva) | 'gateway'
 *   source_message_id      = id del mensaje en su tabla de origen
 *                            (whatsapp_messages para gateway; == message_id para marketing)
 *   source_conversation_id = id de la conversación en su tabla de origen
 *
 * La ruta de Marketing NO cambia de comportamiento (todo lo existente queda
 * source='marketing'); la idempotencia/dedup del gateway se hace por
 * (source, source_message_id).
 */
return new class extends Migration
{
    private array $tables = ['whatsapp_payment_extractions', 'whatsapp_identification_sessions'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            if (!Schema::hasTable($t)) {
                continue;
            }
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (!Schema::hasColumn($t, 'source')) {
                    $table->string('source', 20)->default('marketing')->index()->after('id');
                }
                if (!Schema::hasColumn($t, 'source_message_id')) {
                    $table->unsignedBigInteger('source_message_id')->nullable()->after('source');
                }
                if (!Schema::hasColumn($t, 'source_conversation_id')) {
                    $table->unsignedBigInteger('source_conversation_id')->nullable()->after('source_message_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            if (!Schema::hasTable($t)) {
                continue;
            }
            Schema::table($t, function (Blueprint $table) use ($t) {
                foreach (['source_conversation_id', 'source_message_id', 'source'] as $col) {
                    if (Schema::hasColumn($t, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
