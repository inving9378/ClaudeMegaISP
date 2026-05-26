<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('marketing_pipelines')->onDelete('cascade');
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->string('color', 7)->default('#888888');
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->string('description', 255)->nullable();
            $table->unsignedTinyInteger('default_score')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['pipeline_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_pipeline_stages');
    }
};
