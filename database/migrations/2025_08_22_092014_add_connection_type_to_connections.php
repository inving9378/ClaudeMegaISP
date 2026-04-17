<?php

use App\Models\MapDevicePort;
use App\Models\MapDevicePortConnection;
use App\Models\MapFiber;
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
        DB::statement('delete from map_devices_ports_connections');
        DB::statement('update map_devices_ports set connected=0');
        Schema::table('map_devices_ports_connections', function (Blueprint $table) {
            $table->string('connection_type')->after('to_id')->default('port-to-port');
            $table->string('from_element')->after('to_id');
            $table->string('to_element')->after('from_element');
            $table->json('data')->nullable()->after('layer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_devices_ports_connections', function (Blueprint $table) {
            $table->dropColumn([
                'connection_type',
                'data',
                'from_element',
                'to_element'
            ]);
        });
    }
};
