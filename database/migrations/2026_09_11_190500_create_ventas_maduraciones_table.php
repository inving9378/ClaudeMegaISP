<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de maduración por periodo/colaborador (item #9990799, Ola 1 de la adenda #9990796):
 * meta, candado de suficiencia, faltante, netting y reverso — el cálculo real (reglas R1…R30
 * y los 12 casos TC1…TC12) lo implementa #9990783, posterior a este item. Aquí solo el esquema.
 *
 * `reverso_de_id` auto-referencia: si esta fila es el reverso de una maduración anterior por
 * pago tardío, apunta a la fila original (que NO se edita — el ledger es inmutable).
 *
 * `colaborador_id` NULLABLE a propósito (identidad unificada #9990778 aún no lista).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_maduraciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id')->nullable();
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->date('periodo_start');
            $table->date('periodo_end');
            $table->decimal('meta_monto', 12, 2)->nullable();
            $table->decimal('monto_acumulado', 12, 2)->nullable();
            $table->boolean('candado_suficiencia')->default(false);
            $table->decimal('faltante_monto', 12, 2)->nullable();
            $table->boolean('netting_aplicado')->default(false);
            $table->unsignedBigInteger('reverso_de_id')->nullable();
            $table->enum('estado', ['pendiente', 'madurada', 'revertida'])->default('pendiente');
            $table->timestamps();

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('set null');
            $table->foreign('venta_id')->references('id')->on('ventas_ventas')->onDelete('set null');
            $table->foreign('reverso_de_id')->references('id')->on('ventas_maduraciones')->onDelete('set null');
            $table->index(['periodo_start', 'periodo_end']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_maduraciones');
    }
};
