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
        Schema::table('general_accounting_incomes', function (Blueprint $table) {
            $table->unsignedBigInteger('operation_id')->nullable();
        });
        Schema::table('general_accounting_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('operation_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_accounting_incomes', function (Blueprint $table) {
            $table->dropColumn('operation_id');
        });
        Schema::table('general_accounting_expenses', function (Blueprint $table) {
            $table->dropColumn('operation_id');
        });
    }
};
