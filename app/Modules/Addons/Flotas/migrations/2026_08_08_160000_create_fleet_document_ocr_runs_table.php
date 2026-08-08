<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de lecturas por IA de documentos de vehículo (item #580, Fase 7).
 *
 * Append-only, tabla dedicada: NO se ensucia `fleet_documents` con el crudo de la IA, y queda
 * auditoría de qué leyó el modelo vs. qué confirmó la persona (una corrida existe desde antes de
 * que el documento se guarde, y puede quedar huérfana si el usuario cancela — es a propósito).
 *
 * Es también el candado anti-suplantación: la pantalla manda el `ocr_run_id`, no los campos de
 * OCR. Lo que se persiste como resultado de la IA sale de ESTA tabla, no de lo que diga el cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fleet_document_ocr_runs')) {
            return;
        }

        Schema::create('fleet_document_ocr_runs', function (Blueprint $t) {
            $t->id();

            // Nullable: la corrida ocurre ANTES de que exista el documento. Se liga al guardar.
            $t->foreignId('document_id')->nullable()->constrained('fleet_documents')->nullOnDelete();
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();       // quién disparó la lectura

            $t->boolean('ok')->default(false);
            $t->boolean('needs_review')->default(true);
            $t->string('mime', 60)->nullable();
            $t->unsignedInteger('bytes')->nullable();
            $t->string('file_hash', 64)->nullable();             // sha256, para reconocer el mismo archivo

            $t->json('fields')->nullable();                      // {campo: {value, confidence}}
            $t->json('unreadable')->nullable();
            $t->text('error')->nullable();
            $t->string('provider', 100)->nullable();
            $t->string('model', 100)->nullable();
            $t->longText('raw')->nullable();                     // respuesta cruda, para depurar

            $t->timestamp('created_at')->nullable();

            $t->index('document_id');
            $t->index('vehicle_id');
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_document_ocr_runs');
    }
};
