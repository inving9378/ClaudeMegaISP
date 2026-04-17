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

        Schema::table('distribution_commission_sales', function (Blueprint $table) {
            $table->integer('duration')->change();
            $table->date('date')->after('amount_per_week');
        });

        Schema::table('distribution_commission_sales_amount', function (Blueprint $table) {
            $table->boolean('initial')->after('is_payment')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('distribution_commission_sales', function (Blueprint $table) {
            $table->dropColumn('date');
        });

        Schema::table('distribution_commission_sales_amount', function (Blueprint $table) {
            $table->dropColumn('initial');
        });
    }
};
