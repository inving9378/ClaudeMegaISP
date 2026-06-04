<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sub-paso 3: Evaluaciones prácticas
        Schema::create('talento_practical_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('colaborador_id');
            $table->unsignedBigInteger('evaluator_id');         // talento_colaboradores.id del evaluador
            $table->string('evidence_path', 512)->nullable();   // disk:local antifraude 4a
            $table->boolean('captured_in_app')->default(false);
            $table->decimal('captured_lat', 10, 7)->nullable();
            $table->decimal('captured_lng', 10, 7)->nullable();
            $table->enum('result', ['approved', 'rejected'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('course_id')
                  ->references('id')->on('talento_courses')->onDelete('restrict');
            $table->foreign('colaborador_id')
                  ->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->foreign('evaluator_id')
                  ->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->index(['colaborador_id', 'course_id'], 'tpe_col_course_idx');
        });

        // Sub-paso 4: Certificaciones / badges
        Schema::create('talento_certifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('exam_attempt_id')->nullable();
            $table->unsignedBigInteger('practical_evaluation_id')->nullable();
            $table->string('badge_label', 120);
            $table->timestamp('certified_at')->useCurrent();
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('colaborador_id')
                  ->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->foreign('course_id')
                  ->references('id')->on('talento_courses')->onDelete('restrict');
            $table->foreign('exam_attempt_id')
                  ->references('id')->on('talento_exam_attempts')->onDelete('set null');
            $table->foreign('practical_evaluation_id')
                  ->references('id')->on('talento_practical_evaluations')->onDelete('set null');
            // Un colaborador puede tener sólo una certificación activa por curso
            $table->unique(['colaborador_id', 'course_id'], 'tc_col_course_unique');
            $table->index(['colaborador_id', 'status'], 'tc_col_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_certifications');
        Schema::dropIfExists('talento_practical_evaluations');
    }
};
