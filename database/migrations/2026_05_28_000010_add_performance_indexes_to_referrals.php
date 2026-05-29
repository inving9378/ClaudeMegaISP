<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // referral_commissions: índice para listado admin ordenado + filtros frecuentes
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->index(['status', 'apply_after_at'], 'idx_status_apply_after');
            $table->index(['beneficiary_id', 'status', 'period_year', 'period_month'], 'idx_beneficiary_status_period');
        });

        // referral_rewards: índice para job ExpireReferralRewards y dashboard embajador
        Schema::table('referral_rewards', function (Blueprint $table) {
            $table->index(['status', 'expires_at'],    'idx_reward_status_expires');
            $table->index(['embajador_id', 'status'],  'idx_reward_embajador_status');
        });

        // client_referral_profiles: índice para listado admin ordenado y filtros
        Schema::table('client_referral_profiles', function (Blueprint $table) {
            $table->index(['plan_type', 'is_eligible'],      'idx_plan_eligible');
            $table->index('total_commissions_earned',         'idx_total_earned');
        });

        // Actualizar estadísticas de índices para que el optimizador los use de inmediato
        DB::statement('ANALYZE TABLE referral_commissions, referrals, referral_rewards, client_referral_profiles');
    }

    public function down(): void
    {
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->dropIndex('idx_status_apply_after');
            $table->dropIndex('idx_beneficiary_status_period');
        });

        Schema::table('referral_rewards', function (Blueprint $table) {
            $table->dropIndex('idx_reward_status_expires');
            $table->dropIndex('idx_reward_embajador_status');
        });

        Schema::table('client_referral_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_plan_eligible');
            $table->dropIndex('idx_total_earned');
        });
    }
};
