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
        Schema::table('transactions_sellers', function (Blueprint $table) {
            $table->dropColumn(['total_amount', 'transaction_type']);
            $table->decimal('previous_balance', 8, 2);
            $table->decimal('new_balance', 8, 2);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions_sellers', function (Blueprint $table) {
            $table->dropColumn(['previous_balance', 'new_balance']);
            $table->decimal('total_amount', 8, 2);
            $table->string('transaction_type');
        });
    }
};
