<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #870 (Expediente RH — Hijo D1). Paquete de documentos requeridos por puesto:
 * qué plantillas (de `talento_document_templates`, Hijos B/C ya mergeados) le tocan a cada
 * puesto. 'puesto' es el string libre ya existente en `talento_colaboradores.job_title`
 * (migración 2026_08_26_220000) — NO se crea catálogo de puestos nuevo, a propósito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_puesto_document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('puesto', 100);
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('talento_document_templates')->onDelete('cascade');
            $table->unique(['puesto', 'template_id'], 'tpdt_puesto_template_unique');
            $table->index('puesto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_puesto_document_templates');
    }
};
