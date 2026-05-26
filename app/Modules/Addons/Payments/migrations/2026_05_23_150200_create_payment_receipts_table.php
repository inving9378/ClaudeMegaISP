<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adjuntos por pago — un pago puede tener múltiples comprobantes:
     * XML del CFDI, PDF imprimible, captura de transferencia, payload
     * del webhook como JSON crudo, etc.
     *
     * payments.receipt (varchar singular) ya existe — esta tabla NO lo
     * reemplaza; coexiste para casos multi-archivo donde el campo
     * singular se queda corto (típico en SPEI con XML + PDF + raw payload).
     */
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->string('type', 16); // xml | pdf | image | webhook | other
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size')->default(0); // bytes
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->index(['payment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
