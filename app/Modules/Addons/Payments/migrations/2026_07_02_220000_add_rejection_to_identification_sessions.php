<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 6 (F6.3) — Rechazo de un caso en la cola de Tere (sin aplicar).
 *
 * Cuando Tere RECHAZA un propuesto/escalado (no es válido, ya pagado, etc.), se
 * marca rejected_at/rejected_by/reject_reason y sale de la cola SIN aplicar
 * ningún pago. Aditivas, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_identification_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_identification_sessions', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('applied_payment_id');
            }
            if (!Schema::hasColumn('whatsapp_identification_sessions', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('rejected_at');
            }
            if (!Schema::hasColumn('whatsapp_identification_sessions', 'reject_reason')) {
                $table->string('reject_reason')->nullable()->after('rejected_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_identification_sessions', function (Blueprint $table) {
            foreach (['rejected_at', 'rejected_by', 'reject_reason'] as $col) {
                if (Schema::hasColumn('whatsapp_identification_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
