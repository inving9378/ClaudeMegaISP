<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('parental_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->json('days')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('action', ['block', 'allow'])->default('block');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['profile_id', 'active']);
        });
    }

    public function down(): void { Schema::dropIfExists('parental_schedules'); }
};
