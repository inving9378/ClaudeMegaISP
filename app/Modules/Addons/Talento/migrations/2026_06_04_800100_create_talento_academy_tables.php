<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cursos del LMS
        Schema::create('talento_courses', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('department', 80)->nullable();  // planta, técnicos, admin, etc.
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['active', 'order']);
        });

        // Materiales del curso (texto, video, referencia a estándares/penalizaciones)
        Schema::create('talento_course_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->enum('type', ['text', 'video', 'reference'])->default('text');
            $table->string('title', 200)->nullable();
            $table->text('content')->nullable();            // HTML o markdown para tipo 'text'
            $table->string('video_url', 512)->nullable();  // URL embebible (YouTube, Vimeo, etc.)
            $table->string('file_path', 512)->nullable();  // PDF público (disk:public)
            // Vínculos de lectura a catálogos de 5b y 6a
            $table->unsignedBigInteger('reference_standard_id')->nullable();
            $table->unsignedBigInteger('reference_penalty_type_id')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('course_id')
                  ->references('id')->on('talento_courses')->onDelete('cascade');
            $table->foreign('reference_standard_id')
                  ->references('id')->on('talento_construction_standards')->onDelete('set null');
            $table->foreign('reference_penalty_type_id')
                  ->references('id')->on('talento_penalty_types')->onDelete('set null');
            $table->index(['course_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_course_materials');
        Schema::dropIfExists('talento_courses');
    }
};
