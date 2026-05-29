<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_referral_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->unique();
            $table->string('referral_code', 20)->unique();
            $table->string('referral_link', 255);
            $table->enum('plan_type', ['single_reward', 'multilevel'])->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->decimal('threshold_amount_paid', 10, 2)->default(0);
            $table->timestamp('threshold_covered_at')->nullable();
            $table->tinyInteger('is_eligible')->default(0);
            $table->decimal('total_commissions_earned', 10, 2)->default(0);
            $table->integer('total_rewards_earned')->default(0);
            $table->integer('total_referrals')->default(0);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->index('referral_code', 'idx_referral_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_referral_profiles');
    }
};
