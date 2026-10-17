<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAC-05 (item #510, padre #482): evidencia de cada PDF de cobro que se manda — los bytes exactos
 * (archivo privado en billing.pdf_disk), su sha256 y lo mínimo para saber qué, a quién y en qué
 * estado: generado → encolado → aceptado (el servidor de correo lo recibió) | fallido. «Entregado»
 * no se registra: no hay acuse de entrega del destinatario.
 *
 * Tabla nueva y aditiva: no toca billing_notifications ni las tablas legacy *_emails (guardan HTML,
 * no el PDF).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_documento_evidencias')) {
            return;
        }

        Schema::create('billing_documento_evidencias', function (Blueprint $table) {
            $table->id();
            $table->string('origen', 30);                 // envio_automatico | enviar_manual
            $table->unsignedBigInteger('billing_notification_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->string('documento', 20);              // prefactura | factura | recibo | ticket
            $table->string('canal', 20)->default('correo');
            $table->string('destinatario')->nullable();
            $table->string('asunto')->nullable();
            $table->string('archivo');                    // ruta en billing.pdf_disk
            $table->string('nombre_adjunto');
            $table->char('sha256', 64);
            $table->unsignedInteger('bytes');
            $table->string('estado', 20)->index();        // generado | encolado | aceptado | fallido
            $table->boolean('interceptado')->default(false);
            $table->string('message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('generado_at')->nullable();
            $table->timestamp('encolado_at')->nullable();
            $table->timestamp('aceptado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_documento_evidencias');
    }
};
