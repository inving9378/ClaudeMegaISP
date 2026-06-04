<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_roadmap_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('phase');
            $table->string('title', 200);
            $table->text('scope')->nullable();
            $table->enum('status', ['backlog', 'in_progress', 'done'])->default('backlog');
            $table->date('target_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('phase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_roadmap_items');
    }
};
