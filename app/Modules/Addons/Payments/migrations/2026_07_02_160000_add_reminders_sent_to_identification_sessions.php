<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 3 (F3.4) — Recordatorios por ETAPA.
 *
 * reminders_sent = cuántos recordatorios (nudges) ya se enviaron. Permite el
 * esquema multi-etapa (1h, 5h configurable) idempotente: en cada "tick" solo se
 * manda el recordatorio si su umbral se cruzó y aún no se había mandado.
 *
 * Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_identification_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_identification_sessions', 'reminders_sent')) {
                $table->unsignedTinyInteger('reminders_sent')->default(0)->after('reminder_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_identification_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_identification_sessions', 'reminders_sent')) {
                $table->dropColumn('reminders_sent');
            }
        });
    }
};
