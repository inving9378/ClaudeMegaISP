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
        Schema::create('transactions_sellers', function (Blueprint $table) {
            $table->id();
            $table->timestamp('transaction_date')->useCurrent();
            $table->unsignedBigInteger('method_of_payment');
            $table->foreign('method_of_payment')->references('id')->on('method_of_payments');
            $table->decimal('total_amount', 8, 2);
            $table->string('transaction_type');
            $table->unsignedBigInteger('seller_id');
            $table->foreign('seller_id')->references('id')->on('sellers');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions_sellers');
    }
};
