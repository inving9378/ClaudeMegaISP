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
        Schema::table('olt_onus', function (Blueprint $table) {
            $table->string('mgmt_ip_address')->nullable()->after('catv');
            $table->string('ip_address')->nullable()->after('catv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('olt_onus', function (Blueprint $table) {
            $table->dropColumn(['mgmt_ip_address', 'ip_address']);
        });
    }
};
