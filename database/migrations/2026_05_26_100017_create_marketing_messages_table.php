<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('marketing_conversations')->onDelete('cascade');
            $table->enum('direction', ['inbound', 'outbound']);
            $table->text('content')->nullable();
            $table->enum('content_type', [
                'text', 'image', 'video', 'audio', 'document',
                'location', 'sticker', 'reaction', 'button_reply',
            ])->default('text');
            $table->json('media_paths')->nullable();
            $table->enum('sender', ['lead', 'human_user', 'ai_agent', 'system']);
            $table->unsignedBigInteger('sender_user_id')->nullable()->index();
            $table->string('external_message_id', 150)->nullable()->index();
            $table->unsignedBigInteger('reply_to_message_id')->nullable();
            $table->timestamp('sent_at')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('ai_intent_detected', 80)->nullable();
            $table->decimal('ai_confidence', 4, 3)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_messages');
    }
};
