<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega 'reconciliation' al ENUM marketing_messages.sender.
 *
 * El flujo de conciliación por WhatsApp (ConciliationResponder →
 * SendOutboundMessageJob) despacha los mensajes salientes con sender =
 * 'reconciliation' para distinguirlos del bot de ventas ('ai_agent'). El ENUM
 * original no lo admitía y, con sql_mode=STRICT_TRANS_TABLES, el INSERT del
 * mensaje saliente reventaba (SQLSTATE 1265 "Data truncated for column 'sender'")
 * → la IA nunca respondía. Aditivo: solo suma un valor, no toca los existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `marketing_messages` " .
            "MODIFY `sender` ENUM('lead','human_user','ai_agent','system','reconciliation') NOT NULL"
        );
    }

    public function down(): void
    {
        // Antes de quitar el valor, reasigna las filas que lo usen para no romper
        // el MODIFY (STRICT rechazaría el valor huérfano).
        DB::table('marketing_messages')->where('sender', 'reconciliation')->update(['sender' => 'ai_agent']);

        DB::statement(
            "ALTER TABLE `marketing_messages` " .
            "MODIFY `sender` ENUM('lead','human_user','ai_agent','system') NOT NULL"
        );
    }
};
