<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #838 — historial de versiones por sección. Cada guardado de contenido crea
 * una fila nueva (snapshot); `is_published` marca cuál está vigente. La FK de
 * empresa_manual_sections.published_version_id se agrega después (tabla nueva).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_manual_section_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('empresa_manual_sections')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->longText('content');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['section_id', 'version_number']);
        });

        Schema::table('empresa_manual_sections', function (Blueprint $table) {
            $table->foreign('published_version_id')
                ->references('id')->on('empresa_manual_section_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('empresa_manual_sections', function (Blueprint $table) {
            $table->dropForeign(['published_version_id']);
        });
        Schema::dropIfExists('empresa_manual_section_versions');
    }
};
