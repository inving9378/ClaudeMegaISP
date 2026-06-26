<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asignaciones de una tarea (account-level) a perfiles concretos. Cada hijo tiene
 * su propia fila con su propio estado (pending/completed/rejected) → el balance de
 * cada perfil se calcula desde SUS asignaciones completadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('parental_task_assignments')) {
            return;
        }
        Schema::create('parental_task_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('client_isp_id');

            $table->enum('status', ['pending', 'completed', 'rejected'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('task_id')->references('id')->on('parental_tasks')->onDelete('cascade');
            $table->foreign('profile_id')->references('id')->on('parental_profiles')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('parental_accounts')->onDelete('cascade');

            $table->index(['task_id', 'profile_id']);
            $table->index(['account_id', 'client_isp_id']);
            $table->index('profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parental_task_assignments');
    }
};
