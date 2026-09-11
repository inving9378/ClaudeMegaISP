<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custodia de un prospecto por un colaborador (item #9990799, Ola 1 de la adenda #9990796).
 * Esquema puro: la ventana de 7 días, el máximo de 3 renovaciones y el retorno al pool los
 * implementa el job/servicio de #9990780 (posterior) — aquí solo viven las columnas.
 *
 * `colaborador_id` NULLABLE a propósito (identidad unificada #9990778 aún no lista).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_custodias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prospecto_id');
            $table->unsignedBigInteger('colaborador_id')->nullable();
            $table->timestamp('asignado_at')->useCurrent();
            $table->date('fecha_limite');
            $table->unsignedTinyInteger('renovaciones_usadas')->default(0);
            $table->unsignedTinyInteger('max_renovaciones')->default(3);
            $table->enum('estado', ['activa', 'vencida', 'liberada'])->default('activa');
            $table->timestamp('liberada_at')->nullable();
            $table->timestamps();

            $table->foreign('prospecto_id')->references('id')->on('ventas_prospectos')->onDelete('cascade');
            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('set null');
            $table->index('estado');
            $table->index('fecha_limite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_custodias');
    }
};
