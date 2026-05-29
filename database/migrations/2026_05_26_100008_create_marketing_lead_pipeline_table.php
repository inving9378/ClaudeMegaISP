<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_lead_pipeline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('marketing_leads')->onDelete('cascade');
            $table->foreignId('pipeline_id')->constrained('marketing_pipelines')->onDelete('cascade');
            $table->foreignId('stage_id')->constrained('marketing_pipeline_stages')->onDelete('cascade');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('entered_stage_at');
            $table->timestamp('exited_stage_at')->nullable();
            $table->timestamps();
            $table->unique(['lead_id', 'pipeline_id']);
            $table->index(['stage_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_lead_pipeline');
    }
};
