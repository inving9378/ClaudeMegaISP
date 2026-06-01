<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'done', 'cancelled'])->default('pending');
            $table->enum('priority', ['alta', 'media', 'baja'])->nullable();
            $table->string('target_version', 20)->nullable();
            $table->text('prompt')->nullable();
            $table->integer('position')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_items');
    }
};
