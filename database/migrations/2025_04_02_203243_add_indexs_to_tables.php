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
        Schema::table('payment_by_rule_commissions', function (Blueprint $table) {
            $table->index('type', 'type_index');
            $table->index('start_date', 'start_date_index');
            $table->index('end_date', 'end_date_index');
            $table->index(['start_date', 'end_date'], 'dates_index');
            $table->index(['type', 'start_date', 'end_date'], 'type_and_dates_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_by_rule_commissions', function (Blueprint $table) {
            $table->dropIndex('type_index');
            $table->dropIndex('start_date_index');
            $table->dropIndex('end_date_index');
            $table->dropIndex('dates_index');
            $table->dropIndex('type_and_dates_index');
        });
    }
};
