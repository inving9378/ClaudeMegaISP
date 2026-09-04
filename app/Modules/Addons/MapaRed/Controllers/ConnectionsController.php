<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedCutFiber;
use App\Modules\Addons\MapaRed\Models\MapaRedDevicePortConnection;
use App\Modules\Addons\MapaRed\Models\MapaRedFiber;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Repositories\MapaRedConnectionRepository;
use App\Modules\Addons\MapaRed\Support\LayerConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Espejo de App\Modules\Addons\Mapas\Controllers\Geo\ConnectionsController, apuntando a
 * los modelos mapared_* (MR-06a-2, item #9990334). Ver docs/mapared-mr06-checklist-paridad-item-942.md
 * sección 1.
 */
class ConnectionsController extends Controller
{
    use LayerConfig;

    protected $repository;

    public function __construct()
    {
        $this->repository = new MapaRedConnectionRepository();
    }

    public function updateCut($layerId, $fiberId, $input, $sate = 'close')
    {
        $object = MapaRedCutFiber::where('layer_id', $layerId)->where('fiber_id', $fiberId)->where('current_input', $input)->first();
        if ($object) {
            $object->state = $sate;
            $object->save();
        }
    }

    public function store(Request $request)
    {
        $object = $this->repository->create($request->all());
        if ($object->from_type === MapaRedFiber::class) {
            $this->updateCut($object->layer_id, $object->from_id, $object->from_input);
        } else if ($object->to_type === MapaRedFiber::class) {
            $this->updateCut($object->layer_id, $object->to_id, $object->to_input);
        }
        if (isset($object->from->device) && $object->from->device->type === 'olt') {
            $object->from->zone = $object->data['zone'];
            $object->from->save();
        } else if (isset($object->to->device) && $object->to->device->type === 'olt') {
            $object->to->zone = $object->data['zone'];
            $object->to->save();
        }
        return $this->layerConfig($object->layer);
    }

    public function update(Request $request, $id)
    {
        $object = $this->repository->find($id);
        $object = $this->repository->update($object, $request->all());
        return response()->json($object);
    }

    public function destroy($id)
    {
        $object = MapaRedDevicePortConnection::find($id);
        $layer = $object->layer;

        if ($object->from_type === MapaRedFiber::class) {
            $this->updateCut($layer->id, $object->from_id, $object->from_input, 'open');
        }
        if ($object->to_type === MapaRedFiber::class) {
            $this->updateCut($layer->id, $object->to_id, $object->to_input, 'open');
        }
        $object->delete();

        return $this->layerConfig($layer);
    }

    public function connectionsMultiple(Request $request, $id)
    {
        $date = now();
        $connections = [];
        $ids = [];
        foreach ($request->connections as $c) {
            $c['data'] = json_encode($c['data']);
            $c['created_at'] = $date;
            $c['updated_at'] = $date;
            $connections[] = $c;
            if ($c['from_type'] === MapaRedFiber::class) {
                $ids[] = [
                    'id' => $c['from_id'],
                    'input' => $c['from_input']
                ];
            }
            if ($c['to_type'] === MapaRedFiber::class) {
                $ids[] = [
                    'id' => $c['to_id'],
                    'input' => $c['to_input']
                ];
            }
        }
        if (count($connections)) {
            MapaRedDevicePortConnection::insert($connections);
            if (count($ids) > 0) {
                foreach ($ids as $fiber) {
                    $this->updateCut($id, $fiber['id'], $fiber['input']);
                }
            }
        }
        $layer = MapaRedLayer::find($id);
        return $this->layerConfig($layer);
    }

    public function zones()
    {
        $result = DB::select("with zones AS (SELECT distinct REGEXP_SUBSTR(NAME, 'Z([0-9]+)', 1, 1) AS zone FROM nomenclatures) SELECT * FROM zones WHERE zone IS NOT NULL ORDER BY CAST(SUBSTRING_INDEX(zone, 'Z', -1) AS unsigned)");
        return response()->json($result);
    }

    public function cutConnections(Request $request, $id)
    {
        $date = now();
        $cuts = $request->cuts;
        foreach ($cuts as &$c) {
            $c['created_at'] = $date;
            $c['updated_at'] = $date;
        }
        if (count($cuts) > 0) {
            MapaRedCutFiber::insert($cuts);
        }
        $removed = $request->removed;
        if (count($removed) > 0) {
            MapaRedDevicePortConnection::whereIn('id', $removed)->delete();
        }
        $updated = $request->updated;
        if (count($updated) > 0) {
            MapaRedCutFiber::whereIn('id', $updated)->update([
                'state' => 'open'
            ]);
        }
        $layer = MapaRedLayer::find($id);
        return $this->layerConfig($layer);
    }
}
