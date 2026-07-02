<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 3 (F3.4) — Soporte de simulación + recordatorio en las sesiones.
 *
 * - is_simulation: marca las sesiones creadas desde el SIMULADOR para que F4
 *   (aplicación de pagos) NUNCA las confunda con casos reales. Seguridad.
 * - reminder_sent_at: para el recordatorio (nudge) al cliente que no responde;
 *   en el simulador es "acelerable" (se dispara a mano sin esperar).
 *
 * Aditiva, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_identification_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_identification_sessions', 'is_simulation')) {
                $table->boolean('is_simulation')->default(false)->after('id');
            }
            if (!Schema::hasColumn('whatsapp_identification_sessions', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_identification_sessions', function (Blueprint $table) {
            foreach (['is_simulation', 'reminder_sent_at'] as $col) {
                if (Schema::hasColumn('whatsapp_identification_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
