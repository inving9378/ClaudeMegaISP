<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sub-paso 1: Catálogo de tipos de penalización
        Schema::create('talento_penalty_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->enum('category', ['safety', 'aesthetic', 'malpractice', 'other'])->default('other');
            // event = se aplica por incidente puntual; status = se aplica por estado persistente (ej. sin credencial)
            $table->enum('penalty_kind', ['event', 'status'])->default('event');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('reference_image_path', 512)->nullable(); // disk:public
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['category', 'active']);
        });

        // Sub-paso 2: Penalizaciones aplicadas
        Schema::create('talento_penalties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->unsignedBigInteger('penalty_type_id');
            $table->decimal('amount', 10, 2);           // snapshot del tipo al momento de aplicar
            $table->unsignedBigInteger('applied_by');   // colaborador_id del supervisor que aplica
            $table->string('evidence_photo_path', 512)->nullable(); // disk:local antifraude
            $table->decimal('captured_lat', 10, 7)->nullable();
            $table->decimal('captured_lng', 10, 7)->nullable();
            $table->boolean('captured_in_app')->default(false);
            $table->enum('status', ['applied', 'appealed', 'overturned', 'upheld'])->default('applied');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('colaborador_id')
                  ->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->foreign('penalty_type_id')
                  ->references('id')->on('talento_penalty_types')->onDelete('restrict');
            $table->index(['colaborador_id', 'status'], 'tp_col_status_idx');
            $table->index(['applied_by']);
        });

        // Sub-paso 3: Apelaciones
        Schema::create('talento_penalty_appeals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('penalty_id');
            $table->unsignedBigInteger('appealed_by');          // colaborador_id del técnico
            $table->text('reason');
            $table->string('evidence_path', 512)->nullable();   // disk:local
            $table->unsignedBigInteger('reviewed_by')->nullable();  // DEBE ser distinto de applied_by
            $table->enum('decision', ['overturned', 'upheld'])->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();

            $table->foreign('penalty_id')
                  ->references('id')->on('talento_penalties')->onDelete('restrict');
            $table->index(['penalty_id']);
            $table->index(['reviewed_by', 'decision'], 'tpa_reviewer_decision_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_penalty_appeals');
        Schema::dropIfExists('talento_penalties');
        Schema::dropIfExists('talento_penalty_types');
    }
};
