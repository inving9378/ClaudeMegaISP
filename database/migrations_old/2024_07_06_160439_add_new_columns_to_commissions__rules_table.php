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
            $table->integer('commission_percentage_additional')->nullable()->after('conditions');
            $table->decimal('fixed_sales_commission_additional')->nullable()->after('commission_percentage_additional');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions_rules', function (Blueprint $table) {
            $table->dropColumn('commission_percentage_additional');
            $table->dropColumn('fixed_sales_commission_additional');
        });
    }
};
