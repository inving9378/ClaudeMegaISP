<?php

namespace App\Modules\Addons\MapaRed\Traits;

use App\Modules\Addons\MapaRed\Models\MapaRedDevice;
use App\Modules\Addons\MapaRed\Models\MapaRedDevicePortConnection;

/**
 * Espejo de App\Http\Traits\Maps\LayerConfig, apuntando a modelos MapaRed (MR-06a-4, item #9990336).
 */
trait LayerConfig
{
    use LayerRoutes;

    public function layerConfig($layer)
    {
        $devices = MapaRedDevice::with(['devices', 'devices.ports', 'ports'])->where('layer_id', $layer->id)->get();
        $routes = $this->routesByLayer($layer);
        $connections = MapaRedDevicePortConnection::where('layer_id', $layer->id)->get();
        return response()->json([
            'inputs' => $layer->inputs,
            'devices' => $devices,
            'routes' => $routes,
            'connections' => $connections,
        ]);
    }
}
