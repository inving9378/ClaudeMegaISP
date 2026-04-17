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
        Schema::create('payments_sellers', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number');
            $table->timestamp('payment_date');
            $table->string('amount');
            $table->unsignedBigInteger('method_of_payment');
            $table->foreign('method_of_payment')->references('id')->on('method_of_payments');
            $table->string('comment');
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->unsignedBigInteger('seller_id');
            $table->foreign('seller_id')->references('id')->on('sellers');
            $table->unsignedBigInteger('commission_id');
            $table->foreign('commission_id')->references('id')->on('commissions');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments_sellers');
    }
};
