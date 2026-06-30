<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cola de conciliación: pagos/transferencias dudosas que un humano debe resolver
     * (referencia que no casa con cliente, monto distinto, posible duplicado, revisión).
     *
     * Tabla dedicada — NO se reusa `tickets` (helpdesk CRM: topic/priority/group/estado),
     * que es semánticamente soporte al cliente. Mezclar conciliación financiera ahí
     * ensuciaría el helpdesk. Mientras exista una fila status=open, el listado de
     * clientes muestra un banner persistente (requisito de Irving).
     */
    public function up(): void
    {
        Schema::create('reconciliation_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->enum('reason', [
                'unmatched_reference', // la referencia no corresponde a ningún cliente
                'amount_mismatch',     // monto recibido ≠ esperado
                'duplicate',           // posible pago duplicado
                'manual_review',       // marcado a mano para revisar
            ]);
            $table->text('detail')->nullable();
            $table->double('amount')->nullable();
            $table->enum('status', ['open', 'resolved', 'dismissed'])->default('open');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_tickets');
    }
};
