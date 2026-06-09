<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_team', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('team_id');
            $table->timestamps();

            $table->foreign('project_id')
                  ->references('id')->on('projects')
                  ->onDelete('cascade');
            $table->foreign('team_id')
                  ->references('id')->on('teams')
                  ->onDelete('cascade');

            $table->unique(['project_id', 'team_id'], 'uq_project_team');
            $table->index('project_id', 'idx_project_team_project');
            $table->index('team_id', 'idx_project_team_team');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_team');
    }
};
