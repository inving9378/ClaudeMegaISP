<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->foreignId('campaign_content_id')->constrained('campaign_contents')->cascadeOnDelete();
            $table->string('channel', 50);
            $table->dateTime('scheduled_at');
            $table->dateTime('published_at')->nullable();
            $table->enum('status', ['pending', 'published', 'failed', 'skipped'])->default('pending');
            $table->json('response_data')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_schedules');
    }
};
