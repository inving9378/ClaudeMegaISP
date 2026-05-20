<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('parental_profiles')->cascadeOnDelete();
            $table->enum('type', ['time_extra', 'app_unlock', 'points', 'badge'])->default('points');
            $table->integer('value')->default(0);
            $table->string('detail')->nullable();
            $table->foreignId('source_task_id')->nullable()->constrained('parental_tasks')->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'type']);
            $table->index('expires_at');
        });
    }

    public function down(): void { Schema::dropIfExists('parental_rewards'); }
};
