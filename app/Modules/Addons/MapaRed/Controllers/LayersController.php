<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientMainInformation;
use App\Modules\Addons\MapaRed\Models\MapaRedDevice;
use App\Modules\Addons\MapaRed\Models\MapaRedDevicePort;
use App\Modules\Addons\MapaRed\Models\MapaRedDevicePortConnection;
use App\Modules\Addons\MapaRed\Models\MapaRedFiber;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Models\MapaRedLayerRoute;
use App\Modules\Addons\MapaRed\Models\MapaRedProyect;
use App\Modules\Addons\MapaRed\Repositories\MapaRedLayerRepository;
use App\Modules\Addons\MapaRed\Repositories\MapaRedProyectRepository;
use App\Modules\Addons\MapaRed\Support\LayerConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Espejo de App\Modules\Addons\Mapas\Controllers\Geo\LayersController, portado al módulo
 * MapaRed (MR-06a-4, item #9990336), apuntando a los modelos mapared_* en vez de los legacy
 * Map*. Ver docs/mapared-mr06-checklist-paridad-item-942.md sección 1 para el checklist de
 * paridad y las 2 exclusiones ya confirmadas muertas/rotas (devicesFromRack, savePort).
 *
 * `devicesFromRack` NO se porta a propósito: el método no existe en el controller original
 * (ruta rota sin caller, confirmado en MR-01c) — no hay comportamiento que igualar.
 */
class LayersController extends Controller
{
    use LayerConfig;

    protected $repository;
    protected $projectsRepository;

    public function __construct()
    {
        $this->repository = new MapaRedLayerRepository();
        $this->projectsRepository = new MapaRedProyectRepository();
    }

    public function configuration(Request $request, $id)
    {
        $layer = MapaRedLayer::find($id);
        return $this->layerConfig($layer);
    }

    /**
     * Resumen de puertos/empalmes/clientes colgados para la ficha lateral del árbol
     * (MR-23 fase 3, item #9990428). Reusa el mismo esquema legacy mapared_devices*
     * que ya alimenta ServiceBox/JunctionBox/RackConfiguration — el esquema de dominio
     * nuevo (mapared_puertos/mapared_empalmes/mapared_enlaces_servicio) sigue sin
     * backfill de datos reales, así que no aplica todavía como fuente aquí.
     */
    public function resumen($id)
    {
        $deviceIds = MapaRedDevice::where('layer_id', $id)->pluck('id');
        $ports = MapaRedDevicePort::whereIn('device_id', $deviceIds)->get(['client_id', 'connected']);

        $puertosTotal = $ports->count();
        $puertosOcupados = $ports->where('connected', true)->count();

        $clienteIds = $ports->pluck('client_id')->filter()->unique()->values();
        $clientesActivos = $clienteIds->isEmpty()
            ? 0
            : ClientMainInformation::whereIn('id', $clienteIds)->where('estado', 'Activo')->count();

        $empalmes = MapaRedDevicePortConnection::where('layer_id', $id)->count();

        return response()->json([
            'puertos' => [
                'total' => $puertosTotal,
                'ocupados' => $puertosOcupados,
                'libres' => $puertosTotal - $puertosOcupados,
            ],
            'empalmes' => [
                'total' => $empalmes,
            ],
            'clientes' => [
                'total' => $clienteIds->count(),
                'activos' => $clientesActivos,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $layers = DB::select('SELECT id, type, coords, data, text, label, dialog FROM mapared_layers');
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
                MapaRedLayerRoute::create([
                    'route_id' => $r['route'],
                    'layer_id' => $object->id,
                    'input' => $r['input'],
                    'calculate_distance' => $r['calculate_distance'],
                    'real_distance' => $r['real_distance']
                ]);
                $layer = MapaRedLayer::find($r['route']);
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
        $object = MapaRedLayer::find($id);
        $object->delete();
        return response()->json($object);
    }

    public function destroyMultiple(Request $request)
    {
        $layers = $request->input('layers');
        $deleted = [];
        foreach ($layers as $id) {
            $object = MapaRedLayer::find($id);
            $deleted[] = $object;
            $object->delete();
        }
        return response()->json($deleted);
    }

    public function coords(Request $request, $id)
    {
        $object = MapaRedLayer::find($id);
        $object->coords = $request->input('coords');
        $object->distance = $request->input('distance');
        $object->save();
        if (isset($request->updatesRoutes)) {
            foreach ($request->updatesRoutes as $r) {
                MapaRedLayer::find($r['id'])->update([
                    'coords' => $r['coords'],
                    'distance' => $r['distance'],
                ]);
            }
        }
        return response()->json($this->projectsRepository->getDataFromObject($object));
    }

    public function changeClassification(Request $request)
    {
        $map = [
            'MapLayer' => MapaRedLayer::class,
            'MapProyect' => MapaRedProyect::class,
        ];
        $class = $map[$request->type] ?? null;
        $object = $class ? $class::find($request->id) : null;
        if ($object instanceof MapaRedProyect) {
            DB::statement('with recursive subtree as (select id from mapared_proyects where id=? union all select p.id from mapared_proyects p join subtree s on p.parent_id=s.id) update mapared_layers set classification = ? where project_id in (select id from subtree)', [$object->id, $request->classification]);
        } else {
            $object->classification = $request->classification;
            $object->save();
        }
        return response()->json($this->projectsRepository->getNodes());
    }

    public function addClientToServiceBox($client, $box)
    {
        $layer = MapaRedLayer::find($client);
        $layer->service_box_id = $box;
        $layer->save();
        $layer->load('service_box');
        return response()->json($layer->getLineServiceBox());
    }

    public function moveMarker(Request $request, $node, $to = null)
    {
        $object = MapaRedLayer::find($node);
        $object->project_id = $to;
        $object->save();
        $positions = $request->positions;
        if (count($positions['folders']) > 0) {
            foreach ($positions['folders'] as $data) {
                MapaRedProyect::where('id', $data['id'])->update([
                    'level' => $data['level'],
                ]);
            }
        }
        if (count($positions['layers']) > 0) {
            foreach ($positions['layers'] as $data) {
                MapaRedLayer::where('id', $data['id'])->update([
                    'level' => $data['level'],
                ]);
            }
        }
        return response()->json($this->projectsRepository->getNodes());
    }

    public function convertLayersFromProject(Request $request, $id)
    {
        $projects = DB::select('with recursive subtree as (select id from mapared_proyects where id=? union all select p.id from mapared_proyects p join subtree s on p.parent_id=s.id) select id from mapared_layers where project_id in (select id from subtree) and type = ?', [$id, 'marker']);
        $to = $request->to;
        unset($to['element']);
        $projectIds = array_map(function ($item) {
            return $item->id;
        }, $projects);
        MapaRedLayer::whereIn('id', $projectIds)->update($to);
        if ($to['dialog'] === 'service_box') {
            $objects = MapaRedLayer::whereIn('id', $projectIds)->get();
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
        MapaRedLayer::where('id', $id)->update($to);
        if ($to['dialog'] === 'service_box') {
            $object = MapaRedLayer::find($id);
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
        MapaRedLayer::whereIn('id', $request->ids)->update($to);
        if ($to['dialog'] === 'service_box') {
            $objects = MapaRedLayer::whereIn('id', $request->ids)->get();
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
        $selected = $id ? MapaRedLayerRoute::where('layer_id', $id)->get()->pluck('route_id') : [];
        $avaiables = DB::select("SELECT id as value, JSON_UNQUOTE(JSON_EXTRACT(DATA, CONCAT('$.', label))) as label FROM mapared_layers where dialog='route'");
        return response()->json([
            'avaiables' => $avaiables,
            'selected' => $selected
        ]);
    }

    public function assignRoutes(Request $request, $id)
    {
        $object = MapaRedLayer::find($id);
        $current_routes = $object->routes->pluck('route_id');
        $routes = collect($request->routes);
        foreach ($routes as $r) {
            if (!$current_routes->contains($r)) {
                MapaRedLayerRoute::create([
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
        $object = MapaRedLayerRoute::find($id);
        $layer = $object->layer;
        $route = $object->route;
        $connections = MapaRedDevicePortConnection::where('from_route_id', $id)->orWhere('to_route_id', $id)->get();
        MapaRedDevicePortConnection::whereIn('id', $connections->pluck('id'))->delete();
        $object->delete();
        return $this->layerConfig($layer);
    }

    public function updateConnections($layer, $route)
    {
        $connections = MapaRedDevicePortConnection::where('layer_id', $layer)->whereHasMorph('from', [MapaRedFiber::class], function ($query) use ($route) {
            $query->whereHas('layer', fn($q) => $q->where('id', $route));
        })->orWhereHasMorph('to', [MapaRedFiber::class], function ($query) use ($route) {
            $query->whereHas('layer', fn($q) => $q->where('id', $route));
        })->with('from', 'to')->get()->unique('id');
        MapaRedDevicePortConnection::whereIn('id', $connections->pluck('id'))->delete();
        MapaRedLayerRoute::where('layer_id', $layer)->where('route_id', $route)->first()->delete();
    }

    public function changeRoutePosition(Request $request, $id)
    {
        $object = MapaRedLayerRoute::find($id)->update($request->all());
        return response()->json($object);
    }

    public function createInput(Request $request, $id)
    {
        $object = MapaRedLayer::find($id);
        $data = $request->except(['route', 'update_layer', 'coords']);
        $data['route_id'] = $request->route;
        $data['layer_id'] = $id;
        $data['input'] = $request->inputs;
        MapaRedLayerRoute::create($data);
        $object->coords = $request->coords;
        $object->distance = $request->total_distance;
        if ($request->update_layer) {
            $object->inputs = $request->inputs;
            $object->save();
        }
        $layer = MapaRedLayer::find($request->route);
        $layer->coords = $request->coords;
        $layer->distance = $request->total_distance;
        $layer->save();
        return $this->layerConfig($object);
    }

    public function updateInput(Request $request, $id)
    {
        $object = MapaRedLayerRoute::find($id);
        $object->real_distance = $request->real_distance;
        $object->save();
        $other = MapaRedLayerRoute::where('layer_id', $object->layer->id)->where('route_id', $object->route->id)->where('id', '!=', $object->id)->first();
        if ($other) {
            $other->real_distance = $request->real_distance;
            $other->save();
        }
        return $this->layerConfig($object->layer);
    }

    public function updateMarkersDistanceFromRoute(Request $request, $id)
    {
        foreach ($request->markers as $m) {
            MapaRedLayerRoute::where('layer_id', $m['marker'])->where('route_id', $id)->update([
                'calculate_distance' => $m['distance']
            ]);
        }
        return response()->json(['success' => true]);
    }
}
