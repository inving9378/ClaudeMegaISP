<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_item_memory', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('roadmap_item_id')
                  ->unique()
                  ->constrained('roadmap_items')
                  ->onDelete('cascade');
            $table->string('file_path', 255);
            $table->integer('file_size_bytes')->default(0);
            $table->string('content_hash', 64)->nullable();
            $table->integer('session_count')->default(0);
            $table->text('summary_last_session')->nullable();
            $table->timestamp('last_appended_at')->nullable();
            $table->timestamps();

            $table->index('last_appended_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_item_memory');
    }
};
