<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_by_rule', function (Blueprint $table) {
            $table->id();
            $table->date('payment_date');
            $table->unsignedBigInteger('seller_id');
            $table->foreign('seller_id')->references('id')->on('sellers')->cascadeOnDelete();
            $table->unsignedBigInteger('payment_method_id');
            $table->foreign('payment_method_id')->references('id')->on('method_of_payments')->cascadeOnDelete();
            $table->decimal('amount', $precision = 8, $scale = 2)->default(0);
            $table->string('invoice_number');
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_by_rule_commissions', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedBigInteger('payment_id');
            $table->foreign('payment_id')->references('id')->on('payment_by_rule')->cascadeOnDelete();
            $table->unsignedBigInteger('rule_id');
            $table->foreign('rule_id')->references('id')->on('history_sellers_rules')->cascadeOnDelete();
            $table->decimal('amount', $precision = 8, $scale = 2)->default(0);
            $table->string('type');
            $table->json('data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_by_rule_commissions');
        Schema::dropIfExists('payment_by_rule');
    }
};
