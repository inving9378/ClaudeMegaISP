<?php

use App\Models\MapDevice;
use Illuminate\Database\Migrations\Migration;

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
            foreach ($data['service_cards'] as &$card) {
                $card['order'] = 'asc';
            }
            $d->data = $data;
            $d->save();
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
