<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_job_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('job_class', 100)->comment('Short class name (no namespace)');
            $table->string('status', 20)->comment('success | failed');
            $table->unsignedInteger('duration_ms')->comment('Execution time in milliseconds');
            $table->unsignedInteger('records_processed')->default(0);
            $table->text('context')->nullable()->comment('JSON: payload summary or error message');
            $table->timestamp('ran_at')->useCurrent();

            $table->index(['job_class', 'ran_at'], 'idx_job_ran_at');
            $table->index(['status', 'ran_at'],    'idx_status_ran_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_job_metrics');
    }
};
