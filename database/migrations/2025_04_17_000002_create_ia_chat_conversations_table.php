<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('ia_chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title')->default('Chat IA');
            $table->json('messages')->nullable();
            $table->string('context')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('ia_chat_conversations'); }
};
