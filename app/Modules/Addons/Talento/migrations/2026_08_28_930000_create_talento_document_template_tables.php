<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #200 (Expediente RH — Hijo B). Motor de plantillas de documentos de personal,
 * generico y reutilizable por los Hijos C (conversion de los 11 .docx) y D (paquetes por
 * puesto + generacion automatica). Tablas NUEVAS y propias de Talento — NO se reusa la
 * `document_templates` generica de Clientes/CRM: esa no tiene versionado, esta atada al
 * diccionario de variables Cliente/CRM y siempre genera PDF via dompdf, justo lo opuesto al
 * requisito duro "nunca PDF" de este item (decision registrada en la bitacora del item,
 * 2026-08-28).
 *
 * Versionado INMUTABLE: editar el contenido de una plantilla nunca pisa una version existente,
 * siempre inserta una fila nueva en `talento_document_template_versions` y mueve el puntero
 * `current_version_id`. Un documento ya generado (Hijo D/E) referencia la version con la que
 * se genero, asi que sigue viendose igual aunque la plantilla se edite despues.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 50)->nullable(); // ej. "personal" (Hijo C)
            $table->text('description')->nullable();
            // FK real se agrega despues de crear la tabla de versiones (referencia circular).
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('talento_document_template_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedInteger('version_number');
            // HTML con variables {{var.path}} (dot-notation) y bloques repetibles
            // {{#each lista}}...{{item.campo}}...{{/each}} — ver TemplateRenderService.
            $table->longText('content');
            $table->string('change_note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('template_id')->references('id')->on('talento_document_templates')->onDelete('cascade');
            $table->unique(['template_id', 'version_number'], 'tdtv_template_version_unique');
        });

        Schema::table('talento_document_templates', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('talento_document_template_versions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('talento_document_templates', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('talento_document_template_versions');
        Schema::dropIfExists('talento_document_templates');
    }
};
