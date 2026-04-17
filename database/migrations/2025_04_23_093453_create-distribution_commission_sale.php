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
        Schema::create('distribution_commission_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->foreign('sale_id')->references('id')->on('client_main_information')->cascadeOnDelete();
            $table->float('duration');
            $table->float('initial');
            $table->float('percent');
            $table->float('iva');
            $table->float('service');
            $table->float('total_amount');
            $table->float('total_amount_per_week');
            $table->float('total_discount');
            $table->float('discount_per_week');
            $table->float('amount_per_week');
            $table->timestamps();
        });

        Schema::create('distribution_commission_sales_amount', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('distribution_id');
            $table->foreign('distribution_id')->references('id')->on('distribution_commission_sales')->cascadeOnDelete();
            $table->integer('month');
            $table->integer('year');
            $table->float('amount');
            $table->boolean('is_payment')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribution_commission_sales');
        Schema::dropIfExists('distribution_commission_sales_amount');
    }
};
