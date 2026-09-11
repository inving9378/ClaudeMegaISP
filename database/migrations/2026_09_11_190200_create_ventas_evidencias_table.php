<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidencia de trabajo sobre una custodia (item #9990799, Ola 1 de la adenda #9990796).
 * Append-only: sin updated_at, la evidencia no se edita una vez subida.
 *
 * `colaborador_id` NULLABLE a propósito (identidad unificada #9990778 aún no lista).
 * El criterio de qué cuenta como evidencia válida lo define #9990780 (posterior), citando
 * el reglamento cuando exista — aquí `tipo` queda como texto libre, sin catálogo todavía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_evidencias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('custodia_id')->nullable();
            $table->unsignedBigInteger('colaborador_id')->nullable();
            $table->string('tipo', 60);
            $table->text('descripcion')->nullable();
            $table->string('archivo_path', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('custodia_id')->references('id')->on('ventas_custodias')->onDelete('set null');
            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('set null');
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_evidencias');
    }
};
