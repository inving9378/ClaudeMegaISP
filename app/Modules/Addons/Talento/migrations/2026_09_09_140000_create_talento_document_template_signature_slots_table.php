<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990646 (fase 1/2 de 4 — modelo de slots). Declara, por plantilla, CUÁNTAS
 * firmas lleva el documento y en qué bloque va cada una (p.ej. Contrato = 'empresa' +
 * 'trabajador'). Aditiva: una plantilla sin filas aquí sigue comportándose como hoy (1 sola
 * firma, columnas legado de talento_employee_documents) — ver
 * TalentoEmployeeDocumentController::sign()/forColaborador() tras este item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_document_template_signature_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->string('key', 50);
            $table->string('label', 150);
            $table->enum('firmante_tipo', ['admin', 'colaborador']);
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('requerido')->default(true);
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('talento_document_templates')->onDelete('cascade');
            $table->unique(['template_id', 'key'], 'tdtss_template_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_document_template_signature_slots');
    }
};
