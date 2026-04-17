<?php

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
        Schema::table('map_devices_ports_connections', function (Blueprint $table) {
            $table->smallInteger('from_input')->default(0)->after('from_id');
            $table->smallInteger('to_input')->default(0)->after('to_id');
        });

        DB::statement("UPDATE map_devices_ports_connections c INNER JOIN map_layers_routes r ON c.from_route_id=r.id SET c.from_input = r.input WHERE c.from_type=?", [MapFiber::class]);
        DB::statement("UPDATE map_devices_ports_connections c INNER JOIN map_layers_routes r ON c.to_route_id=r.id SET c.to_input = r.input WHERE c.to_type=?", [MapFiber::class]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_devices_ports_connections', function (Blueprint $table) {
            $table->dropColumn([
                'from_input',
                'to_input'
            ]);
        });
    }
};
