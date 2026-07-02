<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 2 (conciliación de pagos por IA) — Sub-paso 2.
 *
 * Guarda el RESULTADO de la extracción por IA de un comprobante recibido por
 * WhatsApp (imagen o PDF), ligado al mensaje y a la conversación de Marketing.
 *
 * ALCANCE F2: SOLO leer/extraer y mostrar. NO aplica pago, NO identifica
 * cliente, NO responde (eso es F3-F4). Esta tabla es la que consumirá F3.
 *
 * Tabla dedicada (no se ensucia marketing_messages). Disparo manual desde la
 * pantalla de revisión — una fila por cada vez que un admin pulsa "Extraer".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_payment_extractions')) {
            return;
        }

        Schema::create('whatsapp_payment_extractions', function (Blueprint $table) {
            $table->id();

            // Vínculo con el mensaje/conversación de Marketing (índice, sin FK dura
            // — mismo criterio que reported_payments.tecnico_id).
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('conversation_id')->nullable();

            $table->string('document_type')->default('spei_transfer'); // perfil usado
            $table->string('source_mime')->nullable();                 // image/jpeg, application/pdf

            $table->boolean('ok')->default(false);   // la IA devolvió estructura válida
            $table->json('fields')->nullable();       // {campo:{value,confidence}}
            $table->json('unreadable')->nullable();   // [campos ilegibles]
            $table->text('error')->nullable();        // mensaje si ok=false
            $table->string('model')->nullable();      // modelo Claude usado
            $table->longText('raw')->nullable();      // respuesta cruda (auditoría)

            $table->unsignedBigInteger('extracted_by')->nullable(); // admin que la disparó
            $table->timestamp('extracted_at')->nullable();

            $table->timestamps();

            $table->index('message_id');
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_payment_extractions');
    }
};
