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
        $tables = ['map_splitters_ports_connections', 'map_splitters_ports', 'map_splitters', 'map_connections', 'map_olts', 'map_organizer', 'map_switch', 'map_site_racks'];
        foreach ($tables as $t) {
            Schema::dropIfExists($t);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
