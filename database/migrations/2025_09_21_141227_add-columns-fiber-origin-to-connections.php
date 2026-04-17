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
        Schema::table('map_devices_ports_connections', function (Blueprint $table) {
            $table->unsignedBigInteger('from_route_id')->nullable()->after('to_element');
            $table->foreign('from_route_id')->references('id')->on('map_layers_routes')->onDelete('cascade');
            $table->unsignedBigInteger('to_route_id')->nullable()->after('from_route_id');
            $table->foreign('to_route_id')->references('id')->on('map_layers_routes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_devices_ports_connections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_route_id');
            $table->dropConstrainedForeignId('to_route_id');
        });
    }
};
