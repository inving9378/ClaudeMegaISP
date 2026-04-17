<?php

use App\Models\MapCharole;
use App\Models\MapDevice;
use App\Models\MapLayer;
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
        $layers = MapLayer::type('service_box')->get();
        $charoles = [];
        foreach ($layers as $l) {
            for ($i = 0; $i < 3; $i++) {
                $charoles[] = [
                    'name' => "Charole",
                    'layer_id' => $l->id,
                    'type' => "charole",
                ];
            }
        }
        if (count($charoles) > 0) {
            MapDevice::insert($charoles);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
