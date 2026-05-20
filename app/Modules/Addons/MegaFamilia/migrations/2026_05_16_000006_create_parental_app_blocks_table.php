<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_app_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('parental_profiles')->cascadeOnDelete();
            $table->string('package_name');
            $table->string('app_name')->nullable();
            $table->string('category')->nullable();
            $table->boolean('blocked')->default(true);
            $table->time('schedule_start')->nullable();
            $table->time('schedule_end')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'package_name']);
        });
    }

    public function down(): void { Schema::dropIfExists('parental_app_blocks'); }
};
