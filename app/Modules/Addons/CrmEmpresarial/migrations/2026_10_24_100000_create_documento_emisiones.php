<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Empresarial — emisiones de documento (item #604, épica #554, Fase 5).
 *
 * Append-only: una emisión es la evidencia de lo que se generó (snapshot de datos +
 * DOCX + PDF con sha256). El modelo prohíbe update/delete. Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documento_emisiones')) {
            return;
        }

        Schema::create('documento_emisiones', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('folio', 40)->unique();
            $t->unsignedBigInteger('plantilla_version_id')->index();
            $t->string('entidad_tipo', 60)->index();
            $t->unsignedBigInteger('entidad_id')->index();
            $t->json('datos_snapshot');
            $t->string('docx_path')->nullable();
            $t->string('docx_sha256', 64)->nullable();
            $t->string('pdf_path')->nullable();
            $t->string('pdf_sha256', 64)->nullable();
            $t->unsignedBigInteger('emitido_por')->nullable();
            $t->timestamp('emitido_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // Forward-only: las emisiones son evidencia.
    }
};
