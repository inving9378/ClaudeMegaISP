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
        Schema::table('config_finance_notifications', function (Blueprint $table) {
            $table->string('delay_days')->after('attach_invoice')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('config_finance_notifications', function (Blueprint $table) {
            $table->dropColumn('delay_days');
        });
    }
};
