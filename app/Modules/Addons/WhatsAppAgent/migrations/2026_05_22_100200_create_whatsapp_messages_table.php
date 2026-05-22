<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('whatsapp_conversations')
                ->onDelete('cascade');
            $table->foreignId('instance_id')
                ->constrained('whatsapp_instances')
                ->onDelete('cascade');
            $table->enum('direction', ['in', 'out']);
            $table->enum('message_type', [
                'text', 'image', 'document', 'audio', 'video', 'location', 'sticker',
            ])->default('text');
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_filename')->nullable();
            $table->string('media_mime_type')->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            $table->string('evolution_message_id')->unique()->nullable();
            $table->unsignedBigInteger('quoted_message_id')->nullable();
            $table->enum('status', [
                'pending', 'sent', 'delivered', 'read', 'failed', 'received',
            ])->default('pending');
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
            $table->index('direction');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
