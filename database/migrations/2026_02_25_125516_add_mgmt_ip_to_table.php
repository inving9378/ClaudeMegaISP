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
            $table->string('mgmt_ip_subnet_mask')->nullable()->after('mgmt_ip_mode');
            $table->string('mgmt_ip_default_gateway')->nullable()->after('mgmt_ip_mode');
            $table->string('mgmt_ip_dns1')->nullable()->after('mgmt_ip_mode');
            $table->string('mgmt_ip_dns2')->nullable()->after('mgmt_ip_mode');
            $table->string('mgmt_ip_cvlan')->nullable()->after('mgmt_ip_vlan');
            $table->string('mgmt_ip_svlan')->nullable()->after('mgmt_ip_vlan');
            $table->string('mgmt_ip_tag_transform_mode')->nullable()->after('mgmt_ip_vlan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('olt_onus', function (Blueprint $table) {
            $table->dropColumn([
                'mgmt_ip_subnet_mask',
                'mgmt_ip_default_gateway',
                'mgmt_ip_dns1',
                'mgmt_ip_dns2',
                'mgmt_ip_cvlan',
                'mgmt_ip_svlan',
                'mgmt_ip_tag_transform_mode'
            ]);
        });
    }
};
