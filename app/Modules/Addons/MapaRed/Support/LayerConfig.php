<?php

namespace App\Modules\Addons\MapaRed\Support;

use App\Modules\Addons\MapaRed\Models\MapaRedDevice;
use App\Modules\Addons\MapaRed\Models\MapaRedDevicePortConnection;
use App\Modules\Addons\MapaRed\Models\MapaRedLayerRoute;

/**
 * Espejo de App\Http\Traits\Maps\LayerConfig + LayerRoutes, apuntando a los modelos
 * MapaRed* (MR-06a-2, item #9990334). Combina ambos traits legacy en uno solo porque
 * routesByLayer() no se usa fuera de layerConfig() en el módulo viejo.
 */
trait LayerConfig
{
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

    public function routesByLayer($layer)
    {
        $routes = MapaRedLayerRoute::with('route', 'route.fibers')->where('layer_id', $layer->id)->get();
        $routes = $routes->map(function ($item) use ($layer) {
            $route = $item->route;
            foreach ($route->fibers as &$f) {
                $current_cut = $f->cuts()->where('layer_id', $layer->id)->where('fiber_id', $f->id)->where('current_input', $item->input)->first();
                $f['element_id'] = sprintf('polyline-port-%d-%d', $f->id, $item->id);
                $f['current_cut'] = $current_cut;
                $f['current_input'] = $item->input;
            }
            return [
                'route_id' => $item->id,
                'position_x' => $item->position_x,
                'position_y' => $item->position_y,
                'current_input' => $item->input,
                'direction' => $item->direction,
                'calculate_distance' => $item->calculate_distance,
                'real_distance' => $item->real_distance,
                ...$route->toArray()
            ];
        });
        return $routes;
    }
}
