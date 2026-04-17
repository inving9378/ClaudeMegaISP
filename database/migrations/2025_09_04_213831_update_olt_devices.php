<?php

use App\Models\MapDevice;
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
        $devices = MapDevice::where('type', 'olt')->get();
        foreach ($devices as $d) {
            $data = $d->data;
            $cards = $data['cards'] ?? null;
            if ($cards) {
                $ports = $data['ports_x_card'];
                $service_cards = [];
                for ($i = 0; $i < $cards; $i++) {
                    $service_cards[] = [
                        'ports' => $ports
                    ];
                }
                $newData = [
                    'wan_ports' => 0,
                    'console_ports' => 0,
                    'service_cards' => $service_cards
                ];
                $d->data = $newData;
                $d->save();
            }
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
