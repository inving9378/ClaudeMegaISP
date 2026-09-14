<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990831 (Fase 2 del tablero de pendientes, sub-item de #9990816).
 * Auditoría de recordatorios WhatsApp enviados desde el endpoint
 * POST /talento/api/documentos/{docId}/recordar — decisión ya tomada por Irving (q1 del item,
 * opción recomendada): tabla de auditoría con user_id + doc_id + timestamp, no solo una columna
 * "last_reminder_at". `sent_at` alimenta el KPI "última acción" del tablero (Fase 3) y el
 * rate-limit (máximo 1 recordatorio por documento cada 24h).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_document_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_document_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->foreign('employee_document_id', 'tdr_employee_document_id_foreign')
                ->references('id')->on('talento_employee_documents')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['employee_document_id', 'sent_at'], 'tdr_document_sent_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_document_reminders');
    }
};
