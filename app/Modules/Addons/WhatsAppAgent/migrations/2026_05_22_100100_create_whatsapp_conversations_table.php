<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')
                ->constrained('whatsapp_instances')
                ->onDelete('cascade');
            $table->string('contact_number', 30);
            $table->string('contact_name')->nullable();
            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->onDelete('set null');
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->enum('status', ['open', 'closed', 'archived'])->default('open');
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->unique(['instance_id', 'contact_number']);
            $table->index('client_id');
            $table->index('seller_id');
            $table->index('last_message_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
