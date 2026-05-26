<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->foreignId('lead_id')->constrained('marketing_leads')->onDelete('cascade');
            $table->foreignId('channel_id')->constrained('marketing_channels')->onDelete('cascade');
            $table->string('external_thread_id', 150)->nullable()->index();
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->boolean('ai_handled')->default(true);
            $table->enum('status', ['open', 'ai_handling', 'human_review', 'closed'])->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->unsignedSmallInteger('unread_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_conversations');
    }
};
