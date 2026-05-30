<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warroom_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Junta operativa');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_planned_minutes')->default(45);
            $table->integer('duration_actual_seconds')->nullable();
            $table->enum('status', ['draft', 'in_progress', 'paused', 'ended'])->default('draft');
            $table->string('current_section_key')->nullable();
            $table->timestamp('current_section_started_at')->nullable();
            $table->foreignId('moderator_user_id')->constrained('users');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warroom_meetings');
    }
};
