<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warroom_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('warroom_meetings')->cascadeOnDelete();
            $table->string('section_key');
            $table->text('description');
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('priority', ['critico', 'alto', 'medio', 'oportunidad', 'estrategico'])->default('medio');
            $table->string('priority_label')->nullable();
            $table->date('deadline')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('linked_task_id')->nullable();
            $table->timestamps();
            $table->index(['meeting_id', 'section_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warroom_action_items');
    }
};
