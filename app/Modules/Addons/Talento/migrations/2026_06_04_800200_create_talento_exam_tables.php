<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exámenes por curso
        Schema::create('talento_exams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->string('title', 200);
            $table->unsignedTinyInteger('passing_score')->default(70); // porcentaje mínimo para aprobar
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('course_id')
                  ->references('id')->on('talento_courses')->onDelete('cascade');
        });

        // Preguntas del examen
        Schema::create('talento_exam_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id');
            $table->text('question');
            $table->enum('type', ['single', 'multiple', 'true_false'])->default('single');
            $table->json('options');          // ["Opción A", "Opción B", ...]
            $table->json('correct_answer');   // [0] para single/true_false, [0,2] para multiple
            $table->unsignedTinyInteger('points')->default(1);
            $table->unsignedSmallInteger('order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('exam_id')
                  ->references('id')->on('talento_exams')->onDelete('cascade');
            $table->index(['exam_id', 'order']);
        });

        // Intentos del colaborador
        Schema::create('talento_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('colaborador_id');
            $table->unsignedTinyInteger('score')->default(0);      // 0-100
            $table->boolean('passed')->default(false);
            $table->json('answers');           // {question_id: [selected_indices]}
            $table->timestamp('attempted_at')->useCurrent();

            $table->foreign('exam_id')
                  ->references('id')->on('talento_exams')->onDelete('restrict');
            $table->foreign('colaborador_id')
                  ->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->index(['colaborador_id', 'exam_id', 'attempted_at'], 'tea_col_exam_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_exam_attempts');
        Schema::dropIfExists('talento_exam_questions');
        Schema::dropIfExists('talento_exams');
    }
};
