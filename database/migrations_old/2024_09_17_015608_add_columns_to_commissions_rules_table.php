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
        Schema::table('commissions_rules', function (Blueprint $table) {
            $table->string('total_comission')->nullable()->default(0);
            $table->string('number_sales_bonus_commission_required')->nullable()->default(0);
            $table->string('penalty')->nullable()->default(0);
            $table->string('fixed_sales_commission_distribuitors')->nullable()->default(0);
            $table->string('fixed_sales_commission_distribuitors_percent')->nullable()->default(0);
            $table->string('conditions_comission')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions_rules', function (Blueprint $table) {
            $table->dropColumn('total_comission');
            $table->dropColumn('number_sales_bonus_commission_required');
            $table->dropColumn('penalty');
            $table->dropColumn('fixed_sales_commission_distribuitors');
            $table->dropColumn('fixed_sales_commission_distribuitors_percent');
            $table->dropColumn('conditions_comission');
        });
    }
};
