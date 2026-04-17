<?php

use App\Models\MapCutFiber;
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
        $objects = DB::select("SELECT mfc.id FROM map_fibers_cut mfc INNER JOIN map_devices_ports_connections mdpc ON mfc.layer_id=mdpc.layer_id AND ((mfc.fiber_id=mdpc.from_id AND mdpc.from_type=?) OR (mfc.fiber_id=mdpc.to_id AND mdpc.to_type=?))", [MapFiber::class, MapFiber::class]);
        $ids = [];
        foreach ($objects as $o) {
            $ids[] = $o->id;
        }
        MapCutFiber::whereIn('id', $ids)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
