<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('embajador_id');
            $table->unsignedBigInteger('referred_client_id')->unique();
            $table->unsignedBigInteger('prospect_id')->nullable();
            $table->string('chain_path', 255);
            $table->tinyInteger('chain_depth');
            $table->decimal('referred_threshold_amount_paid', 10, 2)->default(0);
            $table->timestamp('referred_threshold_covered_at')->nullable();
            $table->timestamp('commission_window_start')->nullable();
            $table->integer('commissions_paid_count')->default(0);
            $table->enum('status', ['pending_threshold', 'active', 'completed', 'cancelled'])->default('pending_threshold');
            $table->timestamps();

            $table->foreign('embajador_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('referred_client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('prospect_id')->references('id')->on('referral_prospects')->onDelete('set null');

            $table->index('embajador_id', 'idx_referral_embajador');
            $table->index('chain_path', 'idx_referral_chain');
            $table->index('status', 'idx_referral_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
