<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_billing_pauses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->enum('tipo', ['sin_cuota', 'con_cuota']);
            $table->unsignedTinyInteger('meses');
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin');
            $table->dateTime('fecha_fin_real')->nullable();
            $table->enum('estado', [
                'esperando_pago',
                'programada',
                'en_curso',
                'concluida',
                'cancelada',
                'reanudada_anticipada',
            ])->default('programada');
            $table->decimal('cuota_mensual', 10, 2)->nullable();
            $table->decimal('monto_cuota', 10, 2)->nullable();
            $table->unsignedBigInteger('factura_cuota_id')->nullable();
            $table->string('motivo')->nullable();
            $table->enum('canal', ['whatsapp', 'llamada', 'oficina'])->nullable();
            $table->string('evidencia_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients');
            $table->foreign('factura_cuota_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['client_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_billing_pauses');
    }
};
