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
        Schema::create('commissions_rules', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', $precision = 8, $scale = 2)->nullable();
            $table->decimal('sales_commission', $precision = 8, $scale = 2)->nullable();
            $table->decimal('fixed_sales_commission', $precision = 8, $scale = 2)->nullable();
            $table->integer('number_of_prospects')->nullable();
            $table->string('period')->nullable();
            $table->string('zone')->nullable();
            $table->integer('iva')->nullable();
            $table->integer('minimum_sales')->nullable();
            $table->integer('total_bonus')->nullable();
            $table->integer('number_sales_required')->nullable();
            $table->json('conditions')->nullable();
            $table->foreignId('seller_id');
            $table->foreign('seller_id')->references('id')->on('sellers');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions_rules');
    }
};
