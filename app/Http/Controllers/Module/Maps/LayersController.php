<?php

namespace App\Http\Controllers\Module\Maps;

use App\Http\Controllers\Controller;
use App\Http\Traits\Maps\LayerConfig;
use App\Http\Traits\Maps\LayerRoutes;
use App\Models\ClientMainInformation;
use App\Models\MapDevicePortConnection;
use App\Models\MapFiber;
use App\Models\MapLayer;
use App\Models\MapLayerRoute;
use App\Models\MapProyect;
use App\Repositories\MapLayerRepository;
use App\Repositories\MapProyectRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LayersController extends Controller
{
    use LayerConfig;
    use LayerRoutes;

    protected $repository;
    protected $projectsRepository;

    public function __construct()
    {
        $this->repository = new MapLayerRepository();
        $this->projectsRepository = new MapProyectRepository();
    }

    public function configuration(Request $request, $id)
    {
        $layer = MapLayer::find($id);
        return $this->layerConfig($layer);
    }

    public function index(Request $request)
    {
        $layers = DB::select("SELECT id, type, coords, data, text, label, dialog FROM map_layers");
        $processedResults = array_map(function ($item) {
            $data = json_decode($item->data);
            $properties = &$data;
            if ($item->dialog == 'client') {
                $client = ClientMainInformation::find($data->client_id);
                $properties->name = $client->client_name_with_fathers_names;
            }
            return [
                'id' => $item->id,
                'type' => $item->type,
                'text' => $item->text,
                'coords' => json_decode($item->coords),
                'properties' => $properties,
            ];
        }, $layers);
        return response()->json($processedResults);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $data['classification'] = 'project';
        $object = $this->repository->create($data);
        $object->refresh();
        if (isset($request->selected_routes)) {
            foreach ($request->selected_routes as $r) {
                MapLayerRoute::create([
                    'route_id' => $r['route'],
                    'layer_id' => $object->id,
                    'input' => $r['input'],
                    'calculate_distance' => $r['calculate_distance'],
                    'real_distance' => $r['real_distance']
                ]);
                $layer = MapLayer::find($r['route']);
                $layer->coords = $r['coords'];
                $layer->distance = $r['total_distance'];
                $layer->save();
            }
        }
        return response()->json($this->projectsRepository->getDataFromObject($object));
    }

    public function update(Request $request, $id)
    {
        $object = $this->repository->find($id);
        $this->repository->update($object, $request->all());
        return response()->json($this->projectsRepository->getDataFromObject($object));
    }

    public function destroy($id)
    {
        $object = MapLayer::find($id);
        $object->delete();
        return response()->json($object);
    }

    public function destroyMultiple(Request $request)
    {
        $layers = $request->input('layers');
        $deleted = [];
        foreach ($layers as $id) {
            $object = MapLayer::find($id);
            $deleted[] = $object;
            $object->delete();
        }
        return response()->json($deleted);
    }

    public function coords(Request $request, $id)
    {
        $object = MapLayer::find($id);
        $object->coords = $request->input('coords');
        $object->distance = $request->input('distance');
        $object->save();
        if (isset($request->updatesRoutes)) {
            foreach ($request->updatesRoutes as $r) {
                MapLayer::find($r['id'])->update([
                    'coords' => $r['coords'],
                    'distance' => $r['distance'],
                ]);
            }
        }
        return response()->json($this->projectsRepository->getDataFromObject($object));
    }

    public function changeClassification(Request $request)
    {
        $object = sprintf('App\\Models\\%s', $request->type)::find($request->id);
        if ($object instanceof MapProyect) {
            DB::statement('with recursive subtree as (select id from map_proyects where id=? union all select p.id from map_proyects p join subtree s on p.parent_id=s.id) update map_layers set classification = ? where project_id in (select id from subtree)', [$object->id, $request->classification]);
        } else {
            $object->classification = $request->classification;
            $object->save();
        }
        return response()->json($this->projectsRepository->getNodes());
    }

    public function addClientToServiceBox($client, $box)
    {
        $layer = MapLayer::find($client);
        $layer->service_box_id = $box;
        $layer->save();
        $layer->load('service_box');
        return response()->json($layer->getLineServiceBox());
    }

    public function moveMarker(Request $request, $node, $to = null)
    {
        $object = MapLayer::find($node);
        $object->project_id = $to;
        $object->save();
        $positions = $request->positions;
        if (count($positions['folders']) > 0) {
            foreach ($positions['folders'] as $data) {
                MapProyect::where('id', $data['id'])->update([
                    'level' => $data['level'],
                ]);
            }
        }
        if (count($positions['layers']) > 0) {
            foreach ($positions['layers'] as $data) {
                MapLayer::where('id', $data['id'])->update([
                    'level' => $data['level'],
                ]);
            }
        }
        return response()->json($this->projectsRepository->getNodes());
    }

    public function convertLayersFromProject(Request $request, $id)
    {
        $projects = DB::select('with recursive subtree as (select id from map_proyects where id=? union all select p.id from map_proyects p join subtree s on p.parent_id=s.id) select id from map_layers where project_id in (select id from subtree) and type = ?', [$id, 'marker']);
        $to = $request->to;
        unset($to['element']);
        $projectIds = array_map(function ($item) {
            return $item->id;
        }, $projects);
        MapLayer::whereIn('id', $projectIds)->update($to);
        if ($to['dialog'] === 'service_box') {
            $objects = MapLayer::whereIn('id', $projectIds)->get();
            foreach ($objects as $object) {
                if ($object->dialog === 'service_box' && count($object->devices) === 0) {
                    $object->createCharolas();
                }
            }
        }
        return $this->projectsRepository->getLayersFromIds($projectIds);
    }

    public function convertLayerFromLayer(Request $request, $id)
    {
        $to = $request->to;
        unset($to['element']);
        MapLayer::where('id', $id)->update($to);
        if ($to['dialog'] === 'service_box') {
            $object = MapLayer::find($id);
            if (count($object->devices) === 0) {
                $object->createCharolas();
            }
        }
        return $this->projectsRepository->getLayersFromIds([$id]);
    }

    public function convertLayersFromTickeds(Request $request)
    {
        $to = $request->to;
        unset($to['element']);
        MapLayer::whereIn('id', $request->ids)->update($to);
        if ($to['dialog'] === 'service_box') {
            $objects = MapLayer::whereIn('id', $request->ids)->get();
            foreach ($objects as $object) {
                if ($object->dialog === 'service_box' && count($object->devices) === 0) {
                    $object->createCharolas();
                }
            }
        }
        return $this->projectsRepository->getLayersFromIds($request->ids);
    }

    public function avaiablesRoutes(Request $request, $id = null)
    {
        set_time_limit(0);
        ini_set('memory_limit', '8912M');
        $selected = $id ? MapLayerRoute::where('layer_id', $id)->get()->pluck('route_id') : [];
        $avaiables = DB::select("SELECT id as value, JSON_UNQUOTE(JSON_EXTRACT(DATA, CONCAT('$.', label))) as label FROM map_layers where dialog='route'");
        return response()->json([
            'avaiables' => $avaiables,
            'selected' => $selected
        ]);
    }

    public function assignRoutes(Request $request, $id)
    {
        $object = MapLayer::find($id);
        $current_routes = $object->routes->pluck('route_id');
        $routes = collect($request->routes);
        foreach ($routes as $r) {
            if (!$current_routes->contains($r)) {
                MapLayerRoute::create([
                    'route_id' => $r,
                    'layer_id' => $object->id
                ]);
            }
        }
        foreach ($current_routes as $r) {
            if (!$routes->contains($r)) {
                $this->updateConnections($id, $r);
            }
        }
        return $this->layerConfig($object);
    }

    public function unassignRoute($id)
    {
        $object = MapLayerRoute::find($id);
        $layer = $object->layer;
        $route = $object->route;
        $connections = MapDevicePortConnection::where('from_route_id', $id)->orWhere('to_route_id', $id)->get();
        MapDevicePortConnection::whereIn('id', $connections->pluck('id'))->delete();
        $object->delete();
        return $this->layerConfig($layer);
    }

    public function updateConnections($layer, $route)
    {
        $connections = MapDevicePortConnection::where('layer_id', $layer)->whereHasMorph('from', [MapFiber::class], function ($query) use ($route) {
            $query->whereHas('layer', fn($q) => $q->where('id', $route));
        })->orWhereHasMorph('to', [MapFiber::class], function ($query) use ($route) {
            $query->whereHas('layer', fn($q) => $q->where('id', $route));
        })->with('from', 'to')->get()->unique('id');
        MapDevicePortConnection::whereIn('id', $connections->pluck('id'))->delete();
        MapLayerRoute::where('layer_id', $layer)->where('route_id', $route)->first()->delete();
    }

    public function changeRoutePosition(Request $request, $id)
    {
        $object = MapLayerRoute::find($id)->update($request->all());
        return response()->json($object);
    }

    public function createInput(Request $request, $id)
    {
        $object = MapLayer::find($id);
        $data = $request->except(['route', 'update_layer', 'coords']);
        $data['route_id'] = $request->route;
        $data['layer_id'] = $id;
        $data['input'] = $request->inputs;
        MapLayerRoute::create($data);
        $object->coords = $request->coords;
        $object->distance = $request->total_distance;
        if ($request->update_layer) {
            $object->inputs = $request->inputs;
            $object->save();
        }
        $layer = MapLayer::find($request->route);
        $layer->coords = $request->coords;
        $layer->distance = $request->total_distance;
        $layer->save();
        return $this->layerConfig($object);
    }

    public function updateInput(Request $request, $id)
    {
        $object = MapLayerRoute::find($id);
        $object->real_distance = $request->real_distance;
        $object->save();
        $other = MapLayerRoute::where('layer_id', $object->layer->id)->where('route_id', $object->route->id)->where('id', '!=', $object->id)->first();
        if ($other) {
            $other->real_distance = $request->real_distance;
            $other->save();
        }
        return $this->layerConfig($object->layer);
    }

    public function updateMarkersDistanceFromRoute(Request $request, $id)
    {
        foreach ($request->markers as $m) {
            MapLayerRoute::where('layer_id', $m['marker'])->where('route_id', $id)->update([
                'calculate_distance' => $m['distance']
            ]);
        }
        return response()->json(['success' => true]);
    }
}
