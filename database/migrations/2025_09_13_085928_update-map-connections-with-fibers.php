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

        DB::statement("update map_devices_ports_connections set from_element = replace(from_element, '-out-', '-') WHERE from_element LIKE 'polyline-%'");
        DB::statement("update map_devices_ports_connections set from_element = replace(from_element, '-in-', '-') WHERE from_element LIKE 'polyline-%'");
        DB::statement("update map_devices_ports_connections set to_element = replace(to_element, '-out-', '-') WHERE to_element LIKE 'polyline-%'");
        DB::statement("update map_devices_ports_connections set to_element = replace(to_element, '-in-', '-') WHERE to_element LIKE 'polyline-%'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
