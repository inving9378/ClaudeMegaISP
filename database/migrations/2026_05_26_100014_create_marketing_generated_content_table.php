<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_generated_content', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->enum('type', ['copy', 'image', 'video', 'audio_voiceover']);
            $table->unsignedBigInteger('template_id')->nullable()->index();
            $table->string('template_type', 100)->nullable();
            $table->foreignId('source_lead_id')->nullable()->constrained('marketing_leads')->onDelete('set null');
            $table->foreignId('source_campaign_id')->nullable()->constrained('marketing_campaigns')->onDelete('set null');
            $table->unsignedBigInteger('source_plan_id')->nullable()->index();
            $table->json('input_variables')->nullable();
            $table->string('output_path', 500)->nullable();
            $table->string('output_url', 500)->nullable();
            $table->text('output_text')->nullable();
            $table->enum('status', ['pending', 'generating', 'ready', 'failed', 'used'])->default('pending');
            $table->string('generation_engine', 50);
            $table->decimal('generation_cost_usd', 8, 4)->default(0);
            $table->json('generation_metadata')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
            $table->index('source_lead_id');
            $table->index('source_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_generated_content');
    }
};
