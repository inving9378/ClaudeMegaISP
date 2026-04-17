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
            $table->dropColumn('sales_commission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions_rules', function (Blueprint $table) {
            $table->decimal('sales_commission', 8, 2)->nullable();
        });
    }
};
