<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 3 (conciliación de pagos por IA) — Sub-paso F3.1.
 *
 * "Expediente" de identificar al cliente dueño de UN comprobante recibido por
 * WhatsApp. Es la máquina de estados de la conversación de identificación.
 *
 * ALCANCE F3: SOLO identifica y marca QUIÉN es + con qué CERTEZA. NO aplica el
 * pago (eso es F4). El contrato para F4 vive en las columnas method/certainty/
 * resolved_client_id:
 *   - certainty='exact'    → identificado por referencia MEG → F4 puede AUTO-aplicar.
 *   - certainty='proposed' → identificado por nombre → F4 NO auto-aplica; requiere
 *                            confirmación humana (reusa la cola de conciliación
 *                            existente: reported_payments/reconciliation_tickets).
 *
 * Aditiva. No toca el flujo de ventas ni marketing_leads.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_identification_sessions')) {
            return;
        }

        Schema::create('whatsapp_identification_sessions', function (Blueprint $table) {
            $table->id();

            // Vínculos (índices, sin FK dura — criterio de reported_payments).
            $table->unsignedBigInteger('extraction_id');   // whatsapp_payment_extractions
            $table->unsignedBigInteger('conversation_id');  // marketing_conversations
            $table->unsignedBigInteger('message_id')->nullable(); // mensaje del comprobante

            // Máquina de estados.
            // detecting → awaiting_name → awaiting_service → resolved | escalated
            $table->string('state')->default('detecting');

            // Resultado de la identificación (contrato para F4).
            $table->string('method')->nullable();     // meg | name_single | name_disambiguated
            $table->string('certainty')->nullable();  // exact (MEG) | proposed (nombre)
            $table->unsignedBigInteger('resolved_client_id')->nullable();
            $table->json('candidate_client_ids')->nullable(); // homónimos en desambiguación

            // Control de la conversación.
            $table->unsignedTinyInteger('attempts')->default(0); // tope 2 → escala
            $table->timestamp('expires_at')->nullable();          // duración configurable (12h)

            // Escalamiento a humano (Tere).
            $table->unsignedBigInteger('escalated_to')->nullable();
            $table->string('escalation_reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable(); // admin que la disparó
            $table->timestamps();

            $table->index('extraction_id');
            $table->index('conversation_id');
            $table->index('resolved_client_id');
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_identification_sessions');
    }
};
