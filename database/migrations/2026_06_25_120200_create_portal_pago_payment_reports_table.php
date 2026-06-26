<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal de Pago Meganet — reportes de pago (clave de rastreo SPEI + CEP).
 *
 * El cliente reporta la clave de rastreo de su transferencia; el sistema valida
 * el CEP contra Banxico. cep_resultado guarda el JSON crudo de la validacion y
 * cep_xml_path el comprobante CEP descargado. clave_rastreo se indexa para el
 * guard de idempotencia estricta (no doble-conciliar la misma clave) — la regla
 * de idempotencia se aplica en la capa de servicio (Paso 2/3).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_pago_payment_reports')) {
            return;
        }

        Schema::create('portal_pago_payment_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_link_id');
            $table->string('clave_rastreo')->index();
            $table->string('banco_emisor')->nullable();
            $table->date('fecha_operacion')->nullable();
            $table->decimal('monto_reportado', 12, 2);
            $table->string('comprobante_path')->nullable();
            $table->boolean('cep_validado')->default(false);
            $table->json('cep_resultado')->nullable();
            $table->string('cep_xml_path')->nullable();
            $table->enum('estado', [
                'pendiente_validacion',
                'validado',
                'discrepancia',
                'rechazado',
            ])->default('pendiente_validacion');
            // FK logica -> users.id (admin que reviso manualmente).
            $table->unsignedBigInteger('revisado_por')->nullable();
            $table->timestamp('revisado_at')->nullable();
            $table->timestamps();

            $table->foreign('payment_link_id')
                ->references('id')->on('portal_pago_payment_links')
                ->cascadeOnUpdate()->cascadeOnDelete();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_pago_payment_reports');
    }
};
