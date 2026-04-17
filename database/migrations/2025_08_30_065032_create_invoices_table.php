<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique(); // Número de factura
            $table->foreignId('client_id')->constrained()->onDelete('cascade'); // Relación con clients->id
            $table->foreignId('transaction_id')->nullable()->constrained(); // ID de transacción
            $table->foreignId('payment_id')->nullable()->constrained(); // ID de pago
            $table->date('due_date'); // Fecha límite para que el cliente realice el pago
            $table->date('payment_date')->nullable(); // Fecha que se registró el pago
            $table->boolean('is_sent')->default(false); // Si fue enviada al cliente
            $table->decimal('subtotal', 10, 2); // Suma del valor de todos los ítems antes de impuestos
            $table->decimal('tax', 10, 2); // Monto total de impuestos aplicados
            $table->decimal('total', 10, 2); // Monto final a pagar (subtotal + impuesto)
            $table->decimal('pending_balance', 10, 2); // Monto que aún falta por pagar
            $table->enum('status', ['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled'])->default('draft'); // Estado de la factura
            $table->string('payment_method')->nullable(); // Método de pago
            $table->text('notes')->nullable(); // Campo de texto para observaciones
            $table->enum('type', ['payment', 'proforma']); // Tipo de factura
            $table->string('period');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
