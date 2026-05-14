<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->unique()->constrained('parental_profiles')->cascadeOnDelete();
            $table->unsignedInteger('daily_limit_minutes')->default(0);
            $table->unsignedInteger('weekend_limit_minutes')->default(0);
            $table->time('bedtime_start')->nullable();
            $table->time('bedtime_end')->nullable();
            $table->time('school_start')->nullable();
            $table->time('school_end')->nullable();
            $table->boolean('internet_paused')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('parental_rules'); }
};
