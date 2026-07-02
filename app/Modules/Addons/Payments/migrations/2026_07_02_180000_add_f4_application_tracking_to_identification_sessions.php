<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 3.7 — Contrato/traza para Fase 4 (aplicación del pago).
 *
 * La identificación (F3) deja en cada sesión resuelta: resolved_client_id,
 * method (meg/client_id/name_*), certainty (exact|proposed), resolved_multiple_services.
 * F4 leerá esas sesiones para decidir:
 *   - certainty=exact    → puede AUTO-aplicar.
 *   - certainty=proposed → requiere confirmación humana (cola de conciliación).
 *
 * Estas columnas permiten a F4 MARCAR la sesión como aplicada (y con qué pago)
 * para no reprocesarla. Aditivas, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_identification_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_identification_sessions', 'applied_at')) {
                $table->timestamp('applied_at')->nullable()->after('resolved_multiple_services');
            }
            if (!Schema::hasColumn('whatsapp_identification_sessions', 'applied_payment_id')) {
                $table->unsignedBigInteger('applied_payment_id')->nullable()->after('applied_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_identification_sessions', function (Blueprint $table) {
            foreach (['applied_at', 'applied_payment_id'] as $col) {
                if (Schema::hasColumn('whatsapp_identification_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
