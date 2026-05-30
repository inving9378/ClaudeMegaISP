<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warroom_insights_cache', function (Blueprint $table) {
            $table->id();
            $table->string('view_key');
            $table->string('period');
            $table->json('insights');
            $table->enum('source', ['ai', 'rules'])->default('rules');
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->unique(['view_key', 'period']);
            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warroom_insights_cache');
    }
};
