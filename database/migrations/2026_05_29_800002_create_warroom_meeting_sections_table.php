<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warroom_meeting_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('warroom_meetings')->cascadeOnDelete();
            $table->string('section_key');
            $table->integer('order_position');
            $table->integer('time_planned_seconds');
            $table->integer('time_actual_seconds')->default(0);
            $table->foreignId('presenter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['meeting_id', 'section_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warroom_meeting_sections');
    }
};
