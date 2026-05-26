<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_content_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->string('name', 100);
            $table->enum('category', ['copy_post', 'copy_dm', 'copy_email', 'copy_sms', 'copy_voice_script', 'copy_video_script']);
            $table->string('language', 10)->default('es_MX');
            $table->text('template_text');
            $table->json('variables')->nullable();
            $table->boolean('use_ai')->default(true);
            $table->text('ai_prompt_template')->nullable();
            $table->enum('tone', ['profesional', 'amigable', 'urgente', 'divertido', 'formal'])->default('amigable');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_content_templates');
    }
};
