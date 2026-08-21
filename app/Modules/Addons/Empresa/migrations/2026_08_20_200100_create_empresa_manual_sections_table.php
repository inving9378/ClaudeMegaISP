<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #838 — secciones dentro de un capítulo. `content`/`published_version_id`
 * reflejan lo que hoy se muestra al lector (borrador vs. publicado); el
 * historial completo vive en empresa_manual_section_versions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_manual_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained('empresa_manual_chapters')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('order')->default(0);
            $table->longText('content')->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['chapter_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_manual_sections');
    }
};
