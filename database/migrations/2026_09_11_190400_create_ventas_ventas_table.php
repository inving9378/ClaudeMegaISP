<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ventas del motor de ventas/comisiones (item #9990799, Ola 1 de la adenda #9990796).
 *
 * `colaborador_id` NULLABLE a propósito (identidad unificada #9990778 aún no lista).
 * `esquema_comision_id` referencia el esquema vigente al momento de la venta; el cálculo real
 * (con las reglas R1…R30) lo implementa #9990782, posterior a este item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prospecto_id')->nullable();
            $table->unsignedBigInteger('colaborador_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('esquema_comision_id')->nullable();
            $table->string('modo_pago', 60)->nullable();
            $table->decimal('monto', 12, 2);
            $table->date('fecha_venta');
            $table->enum('estado', ['pendiente', 'confirmada', 'cancelada'])->default('pendiente');
            $table->text('notas')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('prospecto_id')->references('id')->on('ventas_prospectos')->onDelete('set null');
            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('esquema_comision_id')->references('id')->on('ventas_esquemas_comision')->onDelete('set null');
            $table->index('estado');
            $table->index('fecha_venta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_ventas');
    }
};
