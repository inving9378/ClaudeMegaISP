<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_period_adjustments')) {
            return;
        }

        Schema::create('talento_period_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id')->index();
            $table->string('original_type', 60)->nullable();
            $table->unsignedBigInteger('original_id')->nullable();
            $table->date('original_period_start');
            $table->date('original_period_end');
            $table->date('target_period_start');
            $table->date('target_period_end');
            $table->decimal('points_delta', 10, 2)->default(0);
            $table->decimal('amount_delta', 12, 2)->default(0);
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['original_type', 'original_id'], 'tpa_original_ref_idx');
            $table->index(['colaborador_id', 'original_period_start', 'original_period_end'], 'tpa_colab_original_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_period_adjustments');
    }
};
