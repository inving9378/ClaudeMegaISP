<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentación Corporativa — Fase 0, contenido.
 *
 * `dc_documentos`      → el documento vigente de un concepto.
 * `dc_documento_versiones` → INMUTABLE. Subir sobre un concepto nunca sobrescribe:
 *                        crea una versión nueva y la anterior queda intacta.
 * `dc_pendientes`      → a quién le toca conseguir lo que falta y para cuándo.
 *
 * NOTA DELIBERADA — no hay columna `estado` en `dc_documentos`. El estado de
 * vigencia (vigente / por_vencer / vencido) se DERIVA de `vigencia_fin` en un
 * accessor del modelo, que es la única fuente de verdad. Persistirlo crearía una
 * segunda fuente que envejece sola: un documento pasa a `vencido` a medianoche
 * sin que nadie escriba esa fila. Las consultas filtran por `vigencia_fin`, que
 * va indexada justo para eso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dc_documentos')) {
            Schema::create('dc_documentos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->foreignId('concepto_id')->constrained('dc_conceptos')->cascadeOnDelete();
                $table->string('titulo');
                // El archivo en disco se llama con este UUID; el nombre humano vive en BD.
                $table->uuid('archivo_uuid');
                $table->string('archivo_nombre_original');
                $table->string('mime', 120);
                $table->unsignedBigInteger('bytes')->default(0);
                $table->char('hash', 64)->comment('SHA-256 del archivo');
                $table->unsignedInteger('version_actual')->default(1);
                $table->date('vigencia_inicio')->nullable();
                $table->date('vigencia_fin')->nullable();
                $table->string('folio')->nullable();
                $table->string('contraparte')->nullable();
                $table->enum('confidencialidad', ['interna', 'restringida', 'critica'])->default('interna');
                $table->text('notas')->nullable();
                $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['empresa_id', 'concepto_id']);
                $table->index('vigencia_fin');
                $table->index('archivo_uuid');
            });
        }

        if (! Schema::hasTable('dc_documento_versiones')) {
            Schema::create('dc_documento_versiones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->foreignId('documento_id')->constrained('dc_documentos')->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->uuid('archivo_uuid');
                $table->string('archivo_nombre_original');
                $table->string('mime', 120);
                $table->unsignedBigInteger('bytes')->default(0);
                $table->char('hash', 64)->comment('SHA-256 del archivo de ESTA versión');
                $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
                $table->text('nota_cambio')->nullable();
                // Append-only: sólo se sella la creación. Sin updated_at, sin soft delete.
                $table->timestamp('created_at')->nullable();

                $table->unique(['documento_id', 'version']);
                $table->index('archivo_uuid');
            });
        }

        if (! Schema::hasTable('dc_pendientes')) {
            Schema::create('dc_pendientes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->foreignId('concepto_id')->constrained('dc_conceptos')->cascadeOnDelete();
                $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('fecha_compromiso')->nullable();
                $table->enum('estado', ['pendiente', 'en_proceso', 'entregado', 'no_aplica'])
                    ->default('pendiente');
                $table->text('comentarios')->nullable();
                $table->timestamp('recordatorio_enviado_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['empresa_id', 'estado']);
                $table->index(['responsable_user_id', 'estado']);
                $table->index('concepto_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dc_pendientes');
        Schema::dropIfExists('dc_documento_versiones');
        Schema::dropIfExists('dc_documentos');
    }
};
