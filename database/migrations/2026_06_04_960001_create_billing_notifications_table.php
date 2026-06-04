<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->enum('document_type', ['prefactura', 'ticket', 'recibo']);
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('email')->nullable();
            $table->string('subject')->nullable();
            // Estado del envío: en_espera → enviado | cancelado | error
            $table->enum('status', ['en_espera', 'enviado', 'cancelado', 'error'])->default('en_espera');
            // Ventana de retención: nunca menor a 12 h (validado a nivel DB y aplicación)
            $table->unsignedSmallInteger('window_hours')->default(12);
            $table->timestamp('notificar_despues_de')->nullable();
            $table->timestamp('enviado_at')->nullable();
            // Al corregir el documento se reinicia la ventana y se registra aquí
            $table->timestamp('regenerated_at')->nullable();
            $table->text('error_log')->nullable();
            // Si send_on_generate=false el registro existe pero nunca se procesa para envío
            $table->boolean('send_by_email')->default(true);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');

            $table->index(['status', 'notificar_despues_de']);
            $table->index(['client_id', 'document_type']);
            $table->index(['invoice_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_notifications');
    }
};
