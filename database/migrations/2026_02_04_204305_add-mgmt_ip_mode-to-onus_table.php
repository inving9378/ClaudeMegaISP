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
        Schema::table(
            'olt_onus',
            function (Blueprint $table) {
                $table->string('mgmt_ip_mode')->nullable()->after('wan_mode');
                $table->string('voip_service')->nullable()->after('signal_1490');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(
            'olt_onus',
            function (Blueprint $table) {
                $table->dropColumn(['mgmt_ip_mode', 'voip_service']);
            }
        );
    }
};
