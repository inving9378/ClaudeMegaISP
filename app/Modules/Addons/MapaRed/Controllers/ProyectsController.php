<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientMainInformation;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Models\MapaRedProyect;
use App\Modules\Addons\MapaRed\Repositories\MapaRedProyectRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Espejo de App\Modules\Addons\Mapas\Controllers\Geo\ProyectsController, apuntando a
 * los modelos mapared_* (MR-06a-5, item #9990337). Ver docs/mapared-mr06-checklist-paridad-item-942.md
 * sección 1.
 */
class ProyectsController extends Controller
{
    protected $repository;

    public function __construct()
    {
        $this->repository = new MapaRedProyectRepository();
    }

    public function index()
    {
        $data = $this->repository->getNodes();
        return response()->json($data);
    }

    public function store(Request $request)
    {
        $object = $this->repository->create($request->all());
        return response()->json($this->repository->getDataFromObject($object));
    }

    public function update(Request $request, $id)
    {
        $object = $this->repository->find($id);
        $this->repository->update($object, $request->all());
        return response()->json($this->repository->getDataFromObject($object));
    }

    public function destroy($id)
    {
        $object = $this->repository->find($id);
        $this->repository->delete($object);
        return response()->json([
            'object' => $object,
            'nodes' => $this->repository->getNodes()
        ]);
    }

    public function clients(Request $request)
    {
        $data = ClientMainInformation::query();
        $current = null;
        if ($request->filter) {
            $data = $data->whereRaw("CONCAT(name, ' ', father_last_name, ' ', mother_last_name) LIKE ?", ["%{$request->filter}%"]);
        }
        if ($request->current) {
            $current = ClientMainInformation::find($request->current);
        }
        $data = $data->paginate($request->pageSize, ['*'], 'page', $request->page);
        return response()->json([
            'options' => $data->items(),
            'current' => $current,
            'pagination' => [
                'total' => $data->total(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ]
        ]);
    }

    public function clientsWithoutProject()
    {
        $data = DB::select("SELECT cmi.id, CONCAT(cmi.name, ' ', cmi.father_last_name, ' ', cmi.mother_last_name) text_node, SUBSTRING_INDEX(cmi.geodata, ',', 1) + 0 as lat, SUBSTRING_INDEX(geodata, ',', -1) + 0 as lng  FROM client_main_information cmi  LEFT JOIN mapared_layers l ON cmi.id!=l.layerable_id AND l.layerable_type='App\Models\ClientMainInformation' WHERE cmi.geodata IS NOT null");
        return response()->json($data);
    }

    public function moveFolder(Request $request, $node, $to = null)
    {
        $object = MapaRedProyect::find($node)->update(['parent_id' => $to]);
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
        return response()->json($this->repository->getNodes());
    }
}
