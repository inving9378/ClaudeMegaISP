<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monitoring_case_documents')) {
            return; // idempotente
        }
        Schema::create('monitoring_case_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('monitoring_cases')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->char('file_hash', 64)->index(); // sha256 — detecta si el PDF es idéntico al anterior
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Tabla PROPIA del módulo: seguro eliminarla en la desinstalación.
        Schema::dropIfExists('monitoring_case_documents');
    }
};
