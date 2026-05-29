<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('embajador_id');
            $table->unsignedBigInteger('referral_id');
            $table->enum('type', ['free_month', 'credit'])->default('free_month');
            $table->decimal('plan_value_snapshot', 10, 2);
            $table->enum('status', ['pending', 'available', 'applied', 'expired'])->default('pending');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('applied_invoice_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('embajador_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('referral_id')->references('id')->on('referrals')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
