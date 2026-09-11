<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Movimientos del ledger del motor de ventas (item #9990799, Ola 1 de la adenda #9990796).
 *
 * NO duplica `talento_ledger_entries` (ya existe, genérica y polimórfica — la usa la
 * compensación de Talento). Esta tabla es el snapshot congelado propio del dominio de ventas
 * (venta + regla aplicada + esquema usado en el momento) que #9990782 va a crear junto con su
 * fila espejo en `talento_ledger_entries` (referenciada ahí vía reference_type/reference_id).
 * Separar el snapshot de dominio del asiento genérico evita acoplar el ledger compartido de
 * Talento a columnas específicas de ventas — sigue la regla de servicios compartidos únicos.
 *
 * Sin updated_at: igual que el ledger de Talento, es inmutable una vez creada (el reverso de
 * #9990783 se registra como fila NUEVA, nunca como edición).
 *
 * `colaborador_id` NULLABLE a propósito (identidad unificada #9990778 aún no lista).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_ledger_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id')->nullable();
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->unsignedBigInteger('maduracion_id')->nullable();
            $table->unsignedBigInteger('talento_ledger_entry_id')->nullable();
            $table->enum('tipo', ['devengo', 'reverso', 'netting'])->default('devengo');
            $table->string('regla_aplicada', 20)->nullable();
            $table->decimal('monto', 12, 2);
            $table->boolean('congelado')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('set null');
            $table->foreign('venta_id')->references('id')->on('ventas_ventas')->onDelete('set null');
            $table->foreign('maduracion_id')->references('id')->on('ventas_maduraciones')->onDelete('set null');
            $table->foreign('talento_ledger_entry_id')->references('id')->on('talento_ledger_entries')->onDelete('set null');
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_ledger_movimientos');
    }
};
