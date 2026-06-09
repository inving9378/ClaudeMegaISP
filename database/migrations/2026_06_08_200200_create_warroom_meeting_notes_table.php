<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warroom_meeting_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('warroom_meetings')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('warroom_meeting_sections')->nullOnDelete();
            $table->enum('kind', ['pregunta', 'respuesta', 'nota'])->default('nota');
            $table->text('body');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['meeting_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warroom_meeting_notes');
    }
};
