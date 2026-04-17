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
        Schema::table('client_bundle_services', function (Blueprint $table) {
            $table->boolean('instalation_cost_paid')->nullable()->default(0)->after('deployed');
        });
        Schema::table('client_custom_services', function (Blueprint $table) {
            $table->boolean('instalation_cost_paid')->nullable()->default(0)->after('deployed');
        });
        Schema::table('client_internet_services', function (Blueprint $table) {
            $table->boolean('instalation_cost_paid')->nullable()->default(0)->after('deployed');
        });
        Schema::table('client_voz_services', function (Blueprint $table) {
            $table->boolean('instalation_cost_paid')->nullable()->default(0)->after('deployed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_bundle_services', function (Blueprint $table) {
           $table->dropColumn('instalation_cost_paid');
        });
        Schema::table('client_custom_services', function (Blueprint $table) {
            $table->dropColumn('instalation_cost_paid');
        });
        Schema::table('client_internet_services', function (Blueprint $table) {
            $table->dropColumn('instalation_cost_paid');
        });
        Schema::table('client_voz_services', function (Blueprint $table) {
            $table->dropColumn('instalation_cost_paid');
        });
    }
};
