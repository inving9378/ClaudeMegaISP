<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['olt_onus', 'olt_cards', 'olt_interruption_pons', 'olt_odbs', 'olt_pon_ports', 'olt_speed_profiles', 'olt_type_onus', 'olt_unconfigured_onus', 'olt_uplink_ports', 'olt_vlans', 'olt_zones', 'olts'];
        foreach ($tables as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('olts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('olt_hardware_version')->nullable();
            $table->string('ip')->nullable();
            $table->string('snmp_port')->nullable();
            $table->string('telnet_port')->nullable();
            $table->string('env_temp')->nullable();
            $table->string('uptime')->nullable();
            $table->string('status')->default('unknown');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('olt_cards', function (Blueprint $table) {
            $table->id();
            $table->integer('slot');
            $table->string('type')->nullable();
            $table->string('real_type')->nullable();
            $table->string('ports')->nullable();
            $table->string('software_version')->nullable();
            $table->string('role')->nullable();
            $table->string('status')->default('unknown');
            $table->unsignedBigInteger('olt_id');
            $table->foreign('olt_id')->references('id')->on('olts')->onDelete('cascade');
            $table->timestamp('info_updated')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['olt_id', 'slot'], 'unique_olt_slot_idx');
        });

        Schema::create('olt_pon_ports', function (Blueprint $table) {
            $table->id();
            $table->integer('board');
            $table->integer('pon_port');
            $table->string('pon_type')->nullable();
            $table->string('admin_status')->nullable();
            $table->string('operational_status')->nullable();
            $table->string('onus_count')->nullable();
            $table->string('online_onus_count')->nullable();
            $table->string('average_signal')->nullable();
            $table->string('min_range')->nullable();
            $table->string('max_range')->nullable();
            $table->string('tx_power')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('olt_id');
            $table->foreign('olt_id')->references('id')->on('olts')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['olt_id', 'board', 'pon_port'], 'unique_pon_port_idx');
        });

        Schema::create('olt_uplink_ports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('mode')->nullable();
            $table->string('admin_status')->nullable();
            $table->string('status')->nullable();
            $table->string('vlan_tag')->nullable();
            $table->string('negotiation_auto')->nullable();
            $table->string('mtu')->nullable();
            $table->string('wavelength')->nullable();
            $table->string('temperature')->nullable();
            $table->string('pvid')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('olt_id');
            $table->foreign('olt_id')->references('id')->on('olts')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['olt_id', 'name'], 'unique_olt_name_idx');
        });

        Schema::create('olt_vlans', function (Blueprint $table) {
            $table->id();
            $table->string('vlan')->nullable();
            $table->string('scope')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('olt_id');
            $table->foreign('olt_id')->references('id')->on('olts')->onDelete('cascade');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('olt_interruption_pons', function (Blueprint $table) {
            $table->id();
            $table->integer('board');
            $table->integer('port');
            $table->string('cause')->nullable();
            $table->integer('power_count')->default(0);
            $table->integer('total_onus')->default(0);
            $table->integer('los_count')->default(0);
            $table->timestamp('latest_status_change')->nullable();
            $table->text('pon_description')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('olt_id');
            $table->foreign('olt_id')->references('id')->on('olts')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['olt_id', 'board', 'port'], 'unique_board_port_idx');
        });

        Schema::create('olt_unconfigured_onus', function (Blueprint $table) {
            $table->id();
            $table->string('sn')->unique();
            $table->integer('board');
            $table->integer('port');
            $table->string('pon_type')->nullable();
            $table->string('onu_type_name')->nullable();
            $table->string('pon_description')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('olt_id');
            $table->foreign('olt_id')->references('id')->on('olts')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('olt_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('olt_type_onus', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('pon_type');
            $table->string('capability');
            $table->integer('ethernet_ports');
            $table->integer('wifi_ports');
            $table->integer('voip_ports');
            $table->integer('catv');
            $table->integer('allow_custom_profiles');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('olt_speed_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('speed');
            $table->string('direction')->nullable();
            $table->string('type')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('olt_odbs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->integer('zone_id');
            $table->string('zone_name')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('olt_onus', function (Blueprint $table) {
            $table->id();
            $table->string('sn')->unique();
            $table->string('unique_external_id')->nullable();
            $table->integer('board')->nullable();
            $table->integer('port')->nullable();
            $table->string('administrative_status')->nullable();
            $table->string('address')->nullable();
            $table->string('mode')->nullable();
            $table->string('name')->nullable();
            $table->string('onu')->nullable();
            $table->string('pon_type')->nullable();
            $table->string('signal')->nullable();
            $table->string('status')->nullable();
            $table->string('tr069')->nullable();
            $table->string('tr069_profile')->nullable();
            $table->string('catv')->nullable();
            $table->string('mgmt_ip_service_port')->nullable();
            $table->string('mgmt_ip_vlan')->nullable();
            $table->string('wan_mode')->nullable();
            $table->string('odb_name')->nullable();
            $table->string('longitude')->nullable();
            $table->string('latitude')->nullable();
            $table->string('contact')->nullable();
            $table->float('signal_1310')->nullable();
            $table->float('signal_1490')->nullable();
            $table->json('service_ports')->nullable();
            $table->json('ethernet_ports')->nullable();
            $table->json('wifi_ports')->nullable();
            $table->json('voip_ports')->nullable();

            $table->unsignedBigInteger('onu_type_id')->nullable();
            $table->foreign('onu_type_id')->references('id')->on('olt_type_onus')->onDelete('cascade');
            $table->string('onu_type_name')->nullable();

            $table->unsignedBigInteger('zone_id')->nullable();
            $table->foreign('zone_id')->references('id')->on('olt_zones')->onDelete('cascade');
            $table->string('zone_name')->nullable();

            $table->unsignedBigInteger('olt_id');
            $table->foreign('olt_id')->references('id')->on('olts')->onDelete('cascade');
            $table->string('olt_name')->nullable();

            $table->timestamp('authorization_date')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
