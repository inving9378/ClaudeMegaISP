<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('release_id')->nullable();
            $table->unsignedBigInteger('triggered_by');
            $table->enum('status', ['pending', 'running', 'success', 'failed', 'rolled_back'])->default('pending');
            $table->json('steps')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->string('rollback_to_version')->nullable();
            $table->timestamps();

            $table->foreign('release_id')->references('id')->on('releases')->onDelete('set null');
            $table->foreign('triggered_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_logs');
    }
};
