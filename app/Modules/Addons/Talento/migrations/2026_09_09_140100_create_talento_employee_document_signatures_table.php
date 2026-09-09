<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990646 (fase 1/2 de 4 — firmas por slot). Una fila por
 * (documento × slot_key). Las columnas legado de talento_employee_documents
 * (signature_path/signed_at/signed_by/signature_method, item #9990618) NO se tocan ni se migran
 * — quedan como compat para plantillas sin slots declarados. slot_key no lleva FK a la tabla de
 * slots a propósito: así un documento generado ANTES de declarar los slots de su plantilla no
 * queda huérfano si luego se agregan/renombran slots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_employee_document_signatures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_document_id');
            $table->string('slot_key', 50);
            $table->string('signature_path')->nullable();
            $table->unsignedBigInteger('signed_by')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->enum('signature_method', ['drawn', 'uploaded', 'digital'])->nullable();
            $table->timestamps();

            $table->foreign('employee_document_id', 'teds_employee_document_id_foreign')
                ->references('id')->on('talento_employee_documents')->onDelete('cascade');
            $table->foreign('signed_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['employee_document_id', 'slot_key'], 'teds_document_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_employee_document_signatures');
    }
};
