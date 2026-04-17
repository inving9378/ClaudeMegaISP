<?php

use App\Models\MapDevice;
use App\Models\MapDevicePort;
use App\Models\MapLayer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $portFilter = function ($query) {
            $query->whereNotNull('client_id')
                ->where(function ($connectionQuery) {
                    $connectionQuery->whereHas('connections_to')
                        ->orWhereHas('connections_from');
                });
        };

        $layers = MapLayer::query()
            ->whereHas('devices.ports', $portFilter)
            ->orWhereHas('devices.devices.ports', $portFilter)
            ->with([
                'devices.ports' => $portFilter,
                'devices.devices.ports' => $portFilter,
            ])
            ->get();

        foreach ($layers as $layer) {
            $directPorts = $layer->devices->flatMap->ports;
            $nestedPorts = $layer->devices->flatMap->devices->flatMap->ports;
            $allPorts = $directPorts->concat($nestedPorts);
            $clientIds = $allPorts->pluck('client_id')
                ->unique()
                ->values()
                ->all();

            $device = MapDevice::create([
                'name' => 'Salida drop',
                'type' => 'drop',
                'layer_id' => $layer->id,
            ]);
            $ports = [];
            foreach ($clientIds as $id) {
                $ports[] = [
                    'type' => 'drop_out',
                    'device_id' => $device->id,
                    'client_id' => $id,
                ];
            }
            MapDevicePort::insert($ports);
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
