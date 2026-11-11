<?php

namespace App\Modules\Addons\MapaRed\Support;

use App\Modules\Addons\MapaRed\Models\MapaRedDevice;
use App\Modules\Addons\MapaRed\Models\MapaRedDevicePort;
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
        $devices->each(fn ($device) => $this->ligarPuertosConEquipo($device));
        $routes = $this->routesByLayer($layer);
        $connections = MapaRedDevicePortConnection::where('layer_id', $layer->id)->get();
        // Los puertos de cada conexión calculan element_id/device_type con su equipo:
        // precargarlo aquí evita una consulta por puerto (un site trae cientos).
        $connections->loadMorph('from', [MapaRedDevicePort::class => ['device']]);
        $connections->loadMorph('to', [MapaRedDevicePort::class => ['device']]);
        return response()->json([
            'inputs' => $layer->inputs,
            'devices' => $devices,
            'routes' => $routes,
            'connections' => $connections,
        ]);
    }

    /**
     * Cada puerto necesita su equipo para element_id/device_type y para `port.device`
     * (isOlt() del frontend). Se liga una copia sin relaciones: con la relación completa
     * el JSON entraría en ciclo (equipo → puertos → equipo …) y sin ligar nada se haría
     * una consulta por puerto.
     */
    public function ligarPuertosConEquipo(MapaRedDevice $device): void
    {
        if ($device->relationLoaded('ports')) {
            $equipo = $device->withoutRelations();
            $device->ports->each(fn ($port) => $port->setRelation('device', $equipo));
        }
        if ($device->relationLoaded('devices')) {
            $device->devices->each(fn ($hijo) => $this->ligarPuertosConEquipo($hijo));
        }
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
