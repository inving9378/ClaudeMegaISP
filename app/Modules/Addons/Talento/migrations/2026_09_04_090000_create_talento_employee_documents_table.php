<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #871 (Expediente RH — Hijo D2). Documento generado (render de una
 * `talento_document_templates` + su version vigente al momento) para un colaborador concreto.
 * UNIQUE(colaborador_id, template_id) para que regenerar sea upsert, nunca duplique fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_employee_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('template_version_id')->nullable();
            $table->longText('rendered_html');
            $table->enum('status', ['completo', 'pendiente']);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('talento_document_templates')->onDelete('cascade');
            $table->foreign('template_version_id')->references('id')->on('talento_document_template_versions')->onDelete('set null');
            $table->unique(['colaborador_id', 'template_id'], 'ted_colaborador_template_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_employee_documents');
    }
};
