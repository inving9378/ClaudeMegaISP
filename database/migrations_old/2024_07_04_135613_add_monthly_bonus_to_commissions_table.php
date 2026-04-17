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
        Schema::table('commissions', function (Blueprint $table) {
            $table->decimal('monthly_bonus', 8, 2)->nullable()->after('account_balance');
            $table->integer('monthly_bonus_sales_number')->nullable()->after('monthly_bonus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn('monthly_bonus');
            $table->dropColumn('monthly_bonus_sales_number');
        });
    }
};
