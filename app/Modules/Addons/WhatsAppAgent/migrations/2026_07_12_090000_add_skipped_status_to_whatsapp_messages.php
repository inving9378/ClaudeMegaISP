<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item #197 (WhatsApp Fase 4 — unificar el sender): agrega 'skipped' al enum
 * whatsapp_messages.status. Lo usa EvolutionApiService::sendTextViaApi() cuando
 * el freno maestro whatsapp.sender_enabled está OFF (log-only, sin POST real a
 * Evolution) — se necesita distinguir de 'failed' (que sí implica reintento vía
 * SendWhatsAppMessageJob) para no confundir monitoreo/soporte con fallos reales.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE whatsapp_messages MODIFY COLUMN status ENUM('pending','sent','delivered','read','failed','received','skipped') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE whatsapp_messages MODIFY COLUMN status ENUM('pending','sent','delivered','read','failed','received') DEFAULT 'pending'");
    }
};
