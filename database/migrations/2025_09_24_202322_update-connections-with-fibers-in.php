<?php

use App\Models\MapFiber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            UPDATE map_devices_ports_connections conn
            INNER JOIN map_fibers from_fiber 
                ON conn.from_id = from_fiber.id 
                AND conn.from_type = ?
            INNER JOIN map_layers from_layer 
                ON from_fiber.fiber_id = from_layer.id
            INNER JOIN map_layers_routes from_route 
                ON from_route.layer_id = conn.layer_id 
                AND from_route.route_id = from_layer.id
            SET conn.from_route_id = from_route.id
            WHERE conn.connection_type IN ('fiber-to-fiber', 'fiber-to-port')
            AND conn.from_type = ?
        ", [MapFiber::class, MapFiber::class]);

        DB::statement("
            UPDATE map_devices_ports_connections conn
            INNER JOIN map_fibers to_fiber 
                ON conn.to_id = to_fiber.id 
                AND conn.to_type = ?
            INNER JOIN map_layers from_layer 
                ON to_fiber.fiber_id = from_layer.id
            INNER JOIN map_layers_routes to_route 
                ON to_route.layer_id = conn.layer_id 
                AND to_route.route_id = from_layer.id
            SET conn.to_route_id = to_route.id
            WHERE conn.connection_type IN ('fiber-to-fiber', 'fiber-to-port')
            AND conn.to_type = ?
        ", [MapFiber::class, MapFiber::class]);

        DB::statement(
            "
            UPDATE map_devices_ports_connections 
            SET from_element=CONCAT(from_element,'-',from_route_id) 
            WHERE from_route_id IS NOT null"
        );

        DB::statement(
            "
            UPDATE map_devices_ports_connections 
            SET to_element=CONCAT(to_element,'-',to_route_id) 
            WHERE to_route_id IS NOT null"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
