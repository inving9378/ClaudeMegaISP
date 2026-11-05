<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Empresarial — versiones de plantilla (item #560, épica #554, Fase 2).
 *
 * Aditiva e idempotente. `document_template_versions` es append-only (el
 * contenido publicado no se edita: se crea otra versión). Las columnas nuevas de
 * `document_templates` son NULL: una plantilla sin versiones conserva el
 * comportamiento actual (HTML residencial).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_template_versions')) {
            Schema::create('document_template_versions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('template_id')->index();
                $t->unsignedInteger('version');
                $t->enum('formato', ['html', 'docx'])->default('docx');
                $t->string('archivo_path')->nullable();
                $t->string('archivo_sha256', 64)->nullable();
                $t->json('variables_requeridas')->nullable();
                $t->json('campos_firma')->nullable();
                $t->enum('estado', ['borrador', 'publicada', 'archivada'])->default('borrador');
                $t->unsignedBigInteger('publicado_por')->nullable();
                $t->timestamp('publicado_at')->nullable();
                $t->text('nota')->nullable();
                $t->timestamps();
                $t->unique(['template_id', 'version'], 'dtv_template_version_unique');
            });
        }

        if (Schema::hasTable('document_templates')) {
            Schema::table('document_templates', function (Blueprint $t) {
                if (! Schema::hasColumn('document_templates', 'segmento_id')) {
                    $t->unsignedBigInteger('segmento_id')->nullable()->index();
                }
                if (! Schema::hasColumn('document_templates', 'tipo_persona')) {
                    $t->enum('tipo_persona', ['fisica', 'moral'])->nullable();
                }
                if (! Schema::hasColumn('document_templates', 'tipo_documento')) {
                    $t->string('tipo_documento', 60)->nullable();
                }
                if (! Schema::hasColumn('document_templates', 'categoria')) {
                    $t->string('categoria', 60)->nullable();
                }
                if (! Schema::hasColumn('document_templates', 'version_publicada_id')) {
                    $t->unsignedBigInteger('version_publicada_id')->nullable();
                }
                if (! Schema::hasColumn('document_templates', 'condiciones_aplicacion')) {
                    $t->json('condiciones_aplicacion')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Aditiva: no se revierte para no perder versiones ya publicadas.
    }
};
