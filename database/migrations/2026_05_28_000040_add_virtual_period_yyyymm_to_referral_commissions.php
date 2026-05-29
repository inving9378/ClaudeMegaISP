<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Virtual generated column: YYYY * 100 + MM from period_year / period_month
        DB::statement('ALTER TABLE referral_commissions ADD COLUMN period_yyyymm INT UNSIGNED GENERATED ALWAYS AS (period_year * 100 + period_month) VIRTUAL');
        DB::statement('ALTER TABLE referral_commissions ADD INDEX idx_period_yyyymm (period_yyyymm)');
    }

    public function down(): void
    {
        Schema::table('referral_commissions', function ($table) {
            $table->dropIndex('idx_period_yyyymm');
            $table->dropColumn('period_yyyymm');
        });
    }
};
