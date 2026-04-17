<?php

namespace App\Http\Traits\Maps;

use App\Models\MapLayerRoute;

trait LayerRoutes
{
    public function routesByLayer($layer)
    {

        $routes = MapLayerRoute::with('route', 'route.fibers')->where('layer_id', $layer->id)->get();
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
