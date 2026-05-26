<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->enum('type', ['image', 'video', 'audio', 'music', 'font', 'brand_logo', 'voiceover']);
            $table->enum('category', ['stock', 'brand', 'music_library', 'sfx', 'voiceover', 'ai_generated']);
            $table->string('name', 150);
            $table->string('path', 500);
            $table->string('url', 500)->nullable();
            $table->string('mime_type', 50);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->decimal('duration_seconds', 8, 2)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->json('tags')->nullable();
            $table->enum('license', ['cc0', 'cc_by', 'owned', 'licensed', 'ai_generated']);
            $table->string('source', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'type', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_assets');
    }
};
