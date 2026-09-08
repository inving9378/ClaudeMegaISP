<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base interna para almacenar CFDI 4.0 (item #117, Fase 1 — parte pre-aprobada:
 * "crear únicamente la base interna para almacenar CFDI", sin PAC real conectado).
 *
 * Tabla vacía hasta que exista un adaptador PAC real (Fase 2). Un CFDI corresponde
 * a un Payment (comprobante de ingreso), no a un ClientInvoice/proforma — así lo
 * define TimbradoServiceInterface::emitirFactura(Payment, ClientFiscalData).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_cfdi_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('payment_id')->unique();
            $table->unsignedBigInteger('client_fiscal_data_id')->nullable();

            $table->enum('estado', ['pendiente', 'timbrada', 'cancelada', 'error'])
                ->default('pendiente');
            $table->string('proveedor_pac', 50)->nullable();

            // Identificadores fiscales del CFDI timbrado
            $table->string('uuid', 36)->nullable()->unique();
            $table->string('serie', 20)->nullable();
            $table->string('folio', 20)->nullable();

            // Snapshot de datos fiscales al momento de timbrar (no debe mutar si el
            // cliente edita su RFC después)
            $table->string('rfc_emisor', 13)->nullable();
            $table->string('rfc_receptor', 13)->nullable();
            $table->string('razon_social_receptor', 250)->nullable();
            $table->string('uso_cfdi', 4)->nullable();
            $table->string('regimen_fiscal_receptor', 4)->nullable();

            $table->string('moneda', 5)->default('MXN');
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('iva', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();

            // Artefactos generados
            $table->string('xml_path', 500)->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->string('qr_path', 500)->nullable();
            $table->text('cadena_original')->nullable();
            $table->text('sello_cfdi')->nullable();
            $table->text('sello_sat')->nullable();
            $table->string('no_certificado_cfdi', 25)->nullable();
            $table->string('no_certificado_sat', 25)->nullable();

            $table->timestamp('fecha_timbrado')->nullable();
            $table->string('motivo_cancelacion', 5)->nullable();
            $table->timestamp('cancelada_at')->nullable();

            // Respuesta cruda del PAC (auditoría/debug) y mensaje de error si aplica
            $table->json('respuesta_pac')->nullable();
            $table->text('error_mensaje')->nullable();

            $table->unsignedBigInteger('created_by')->default(0);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('client_fiscal_data_id')->references('id')->on('client_fiscal_data')->onDelete('set null');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_cfdi_invoices');
    }
};
