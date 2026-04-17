<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

use function Livewire\after;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('olt_onus', function (Blueprint $table) {
            $table->integer('onu_type_id')->nullable()->after('onu');
            $table->string('tr069')->nullable()->after('zone_name');
            $table->string('tr069_profile')->nullable()->after('zone_name');
            $table->string('catv')->nullable()->after('zone_name');
            $table->integer('zone_id')->after('status')->nullable();
            $table->string('mgmt_ip_service_port')->nullable()->after('zone_name');
            $table->string('mgmt_ip_vlan')->nullable()->after('zone_name');
            $table->string('wan_mode')->nullable()->after('zone_name');
            $table->string('odb_name')->nullable()->after('zone_name');
            $table->string('longitude')->nullable()->after('zone_name');
            $table->string('latitude')->nullable()->after('zone_name');
            $table->string('olt_name')->nullable()->after('olt_id');
            $table->string('contact')->nullable()->after('zone_name');
            $table->float('signal_1310')->nullable()->after('zone_name');
            $table->float('signal_1490')->nullable()->after('zone_name');
            $table->timestamp('authorization_date')->nullable()->after('last_synced_at');
            $table->json('service_ports')->nullable()->after('authorization_date');
            $table->json('ethernet_ports')->nullable()->after('authorization_date');
            $table->json('wifi_ports')->nullable()->after('authorization_date');
            $table->json('voip_ports')->nullable()->after('authorization_date');
        });

        try {
            Artisan::call('olt:sync-onus');
        } catch (\Throwable $th) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('olt_onus', function (Blueprint $table) {
            $table->dropColumn([
                'onu_type_id',
                'tr069',
                'tr069_profile',
                'catv',
                'zone_id',
                'mgmt_ip_service_port',
                'mgmt_ip_vlan',
                'wan_mode',
                'odb_name',
                'longitude',
                'latitude',
                'olt_name',
                'contact',
                'signal_1310',
                'signal_1490',
                'authorization_date',
                'service_ports',
                'ethernet_ports',
                'wifi_ports',
                'voip_ports'
            ]);
        });
    }
};
