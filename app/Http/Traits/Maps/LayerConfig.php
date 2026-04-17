<?php

namespace App\Http\Traits\Maps;

use App\Models\MapDevice;
use App\Models\MapDevicePort;
use App\Models\MapDevicePortConnection;
use App\Models\MapFiber;

trait LayerConfig
{
    use LayerRoutes;
    public function layerConfig($layer)
    {
        $devices = MapDevice::with(['devices', 'devices.ports', 'ports'])->where('layer_id', $layer->id)->get();
        $routes = $this->routesByLayer($layer);
        $connections = MapDevicePortConnection::where('layer_id', $layer->id)->get();
        return response()->json([
            'inputs' => $layer->inputs,
            'devices' => $devices,
            'routes' => $routes,
            'connections' => $connections,
        ]);
    }
}
