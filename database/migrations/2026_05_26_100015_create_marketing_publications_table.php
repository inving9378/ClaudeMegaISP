<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_publications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->onDelete('set null');
            $table->foreignId('channel_id')->constrained('marketing_channels')->onDelete('cascade');
            $table->foreignId('content_id')->nullable()->constrained('marketing_generated_content')->onDelete('set null');
            $table->text('custom_text')->nullable();
            $table->json('media_paths')->nullable();
            $table->timestamp('scheduled_at')->index();
            $table->timestamp('published_at')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'publishing', 'published', 'failed', 'canceled'])->default('draft');
            $table->string('external_post_id', 100)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->json('engagement')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_publications');
    }
};
