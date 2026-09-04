<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentación Corporativa — Fase 0, bitácora.
 *
 * APPEND-ONLY. Sin `updated_at`, sin `deleted_at`: estas filas se CREAN y se LEEN,
 * jamás se actualizan ni se borran. Es la respuesta viva al apartado XII de la
 * solicitud ("bitácora de accesos a información corporativa"), y una bitácora que
 * se puede editar no es una bitácora.
 *
 * Toda vista de apartado, descarga, exportación e impresión escribe aquí ANTES de
 * servir el contenido. No hay flag para desactivarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dc_accesos_log')) {
            return;
        }

        Schema::create('dc_accesos_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Los tres son nullable porque el acceso puede ser a un apartado
            // completo, a un concepto, o a un documento puntual.
            $table->unsignedBigInteger('apartado_id')->nullable();
            $table->unsignedBigInteger('concepto_id')->nullable();
            $table->unsignedBigInteger('documento_id')->nullable();
            $table->enum('accion', ['ver', 'descargar', 'exportar', 'imprimir']);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            // Justificación de un detalle nominal, formato de exportación, etc.
            $table->json('contexto')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['empresa_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('apartado_id');
            $table->index('concepto_id');
            $table->index('documento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dc_accesos_log');
    }
};
