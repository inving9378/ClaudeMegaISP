<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_web_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('parental_profiles')->cascadeOnDelete();
            $table->string('domain');
            $table->string('category')->nullable();
            $table->boolean('blocked')->default(true);
            $table->timestamps();

            $table->unique(['profile_id', 'domain']);
        });
    }

    public function down(): void { Schema::dropIfExists('parental_web_blocks'); }
};
