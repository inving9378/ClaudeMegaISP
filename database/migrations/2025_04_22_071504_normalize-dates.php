<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_main_information', function (Blueprint $table) {
            $table->dropIndex('idx_client_main_information_activation_date');
        });

        Schema::table('client_main_information', function (Blueprint $table) {
            $table->renameColumn('activation_date', 'activation_date_old');
        });

        Schema::table('client_main_information', function (Blueprint $table) {
            $table->date('activation_date')->nullable()->after('activation_date_old');
        });

        Schema::table('client_main_information', function (Blueprint $table) {
            $table->index('activation_date', 'idx_client_main_information_activation_date');
        });

        DB::statement("UPDATE client_main_information SET activation_date = STR_TO_DATE(SUBSTRING(activation_date_old, 1, 10), '%Y-%m-%d') where SUBSTRING(activation_date_old, 1, 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
        DB::statement("UPDATE client_main_information SET activation_date = STR_TO_DATE(SUBSTRING(activation_date_old, 1, 10), '%d/%m/%Y') where SUBSTRING(activation_date_old, 1, 10) REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$'");

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_main_information', function (Blueprint $table) {
            $table->dropIndex('idx_client_main_information_activation_date');
        });

        Schema::table('client_main_information', function (Blueprint $table) {
            $table->dropColumn('activation_date');
        });

        Schema::table('client_main_information', function (Blueprint $table) {
            $table->renameColumn('activation_date_old', 'activation_date');
        });

        Schema::table('client_main_information', function (Blueprint $table) {
            $table->index('activation_date', 'idx_client_main_information_activation_date');
        });
    }
};
