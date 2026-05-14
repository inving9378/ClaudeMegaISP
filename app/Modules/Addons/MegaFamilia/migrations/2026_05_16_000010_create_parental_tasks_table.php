<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('parental_profiles')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('reward_type', ['time_extra', 'app_unlock', 'points', 'badge'])->default('points');
            $table->integer('reward_value')->default(0);
            $table->string('reward_detail')->nullable();
            $table->integer('points')->default(0);
            $table->enum('status', ['pending', 'completed', 'approved', 'rejected'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('photo_proof')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('parental_tasks'); }
};
