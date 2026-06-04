<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_project_deviations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('colaborador_id');
            $table->decimal('detected_lat', 10, 7);
            $table->decimal('detected_lng', 10, 7);
            $table->decimal('deviation_m', 8, 1);
            $table->unsignedSmallInteger('sustained_minutes');
            $table->timestamp('detected_at');
            $table->boolean('supervisor_notified')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('project_id')->references('id')->on('talento_projects')->onDelete('cascade');
            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('cascade');
            $table->index(['project_id', 'detected_at'], 'tpd_proj_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_project_deviations');
    }
};
