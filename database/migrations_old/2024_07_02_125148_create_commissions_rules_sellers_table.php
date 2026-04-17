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
        if (!Schema::hasTable('commissions_rules_sellers')) {
            Schema::create('commissions_rules_sellers', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('commission_rule_id')->unsigned();
                $table->bigInteger('seller_id')->unsigned();
                $table->foreign('commission_rule_id')->references('id')->on('commissions_rules')->onDelete('cascade');
                $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions_rules_sellers');
    }
};
