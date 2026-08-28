<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CFDI (XML+PDF) ya timbrado fuera del sistema (no hay PAC integrado — ver
 * App\Services\Finance\Timbrado\TimbradoServiceInterface) y adjuntado a una
 * factura para que el Portal Cliente lo muestre/descargue (item roadmap #148,
 * opción A: "portal solo consulta CFDI ya timbrado").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_invoice_cfdi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_invoice_id');
            $table->string('uuid_fiscal', 36)->nullable();
            $table->string('serie', 25)->nullable();
            $table->string('folio', 40)->nullable();
            $table->string('rfc_receptor', 13)->nullable();
            $table->string('xml_path');
            $table->string('pdf_path');
            $table->timestamp('timbrado_at')->nullable();
            $table->timestamp('cancelado_at')->nullable();
            $table->string('motivo_cancelacion')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('client_invoice_id');
            $table->foreign('client_invoice_id')->references('id')->on('client_invoices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_invoice_cfdi');
    }
};
