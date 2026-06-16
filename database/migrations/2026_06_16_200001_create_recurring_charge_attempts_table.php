<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla inmutable: un registro por intento; sin updated_at.
        // El número de intento (attempt_no) permite rastrear el ciclo 1-3 por factura.
        Schema::create('recurring_charge_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('invoice_id');
            $table->string('order_id', 80)->unique();
            $table->string('openpay_charge_id', 100)->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'completed', 'failed']);
            $table->tinyInteger('attempt_no')->unsigned()->default(1);
            $table->smallInteger('error_code')->unsigned()->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['client_id', 'invoice_id']);
            $table->index(['client_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_charge_attempts');
    }
};
