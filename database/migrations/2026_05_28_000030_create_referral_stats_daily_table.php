<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->comment('Fecha del snapshot (YYYY-MM-DD)');
            $table->unsignedInteger('total_embajadores')->default(0);
            $table->unsignedInteger('active_embajadores')->default(0);
            $table->unsignedInteger('new_referrals')->default(0);
            $table->unsignedInteger('converted_referrals')->default(0);
            $table->decimal('commissions_generated', 12, 2)->default(0);
            $table->decimal('commissions_applied', 12, 2)->default(0);
            $table->unsignedInteger('rewards_generated')->default(0);
            $table->unsignedInteger('rewards_applied')->default(0);
            $table->timestamps();

            $table->unique('stat_date', 'uniq_stat_date');
            $table->index('stat_date', 'idx_stat_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_stats_daily');
    }
};
