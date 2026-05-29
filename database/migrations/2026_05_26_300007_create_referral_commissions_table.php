<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('beneficiary_id');
            $table->unsignedBigInteger('referral_id');
            $table->unsignedBigInteger('invoice_id');
            $table->tinyInteger('level');
            $table->decimal('commission_pct', 5, 2);
            $table->decimal('base_amount', 10, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->tinyInteger('period_month');
            $table->smallInteger('period_year');
            $table->enum('status', ['pending', 'approved', 'applied', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('applied_invoice_id')->nullable();
            $table->timestamps();

            $table->foreign('beneficiary_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('referral_id')->references('id')->on('referrals')->onDelete('cascade');

            $table->index(['beneficiary_id', 'period_year', 'period_month'], 'idx_beneficiary_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
    }
};
