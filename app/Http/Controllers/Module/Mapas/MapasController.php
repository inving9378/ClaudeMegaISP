<?php


namespace App\Http\Controllers\Module\Mapas;


use App\Http\Controllers\Controller;
use App\Models\ActiveEquipment;
use App\Models\Box;
use App\Models\BoxInput;
use App\Models\CutFiber;
use App\Models\PassiveEquipment;
use App\Models\Point as ModelsPoint;
use App\Models\Pole;
use App\Models\Port;
use App\Models\Site;
use App\Models\Splitter;
use App\Models\Tray;
use App\Models\Trench;
use App\Repositories\ActiveEquipmentRepository;
use App\Repositories\ActiveEquipmentTypeRepository;
use App\Repositories\BoxInputRepository;
use App\Repositories\BoxRepository;
use App\Repositories\BoxTypeRepository;
use App\Repositories\BrandRepository;
use App\Repositories\CardRepository;
use App\Repositories\CutFiberRepository;
use App\Repositories\MapLinkRepository;
use App\Repositories\MapProyectRepository;
use App\Repositories\PassiveEquipmentRepository;
use App\Repositories\PassiveEquipmentTypeRepository;
use App\Repositories\PointRepository;
use App\Repositories\PoleRepository;
use App\Repositories\PortRepository;
use App\Repositories\PositionRepository;
use App\Repositories\RackRepository;
use App\Repositories\SiteRepository;
use App\Repositories\TableRepository;
use App\Repositories\TrencheTypesRepository;
use App\Repositories\TubeTypeRepository;
use App\Services\MapService;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use MatanYadaev\EloquentSpatial\Objects\Point;

class MapasController extends Controller
{
    protected $ActiveEquipmentRepository;
    protected $ActiveEquipmentTypeRepository;
    protected $BoxRepository;
    protected $BoxTypeRepository;
    protected $BoxInputRepository;
    protected $BrandRepository;
    protected $CutFiberRepository;
    protected $CardRepository;
    protected $MapLinkRepository;
    protected $MapProyectRepository;
    protected $MapService;
    protected $PassiveEquipmentRepository;
    protected $PassiveEquipmentTypeRepository;
    protected $PoleRepository;
    protected $PositionRepository;
    protected $RackRepository;
    protected $SiteRepository;
    protected $TableRepository;
    protected $PointRepository;
    protected $TubeTypeRepository;
    protected $TrencheTypesRepository;
    protected $PortRepository;
    protected $SimpleService;

    public function __construct()
    {
        $this->CardRepository = new CardRepository();
        $this->MapService = new MapService();
        $this->BoxRepository = new BoxRepository();
        $this->BoxTypeRepository = new BoxTypeRepository();
        $this->BoxInputRepository = new BoxInputRepository();
        $this->BrandRepository = new BrandRepository();
        $this->CutFiberRepository = new CutFiberRepository();
        $this->MapProyectRepository = new MapProyectRepository();
        $this->PassiveEquipmentRepository = new PassiveEquipmentRepository();
        $this->PassiveEquipmentTypeRepository = new PassiveEquipmentTypeRepository();
        $this->PointRepository = new PointRepository();
        $this->ActiveEquipmentRepository = new ActiveEquipmentRepository();
        $this->ActiveEquipmentTypeRepository = new ActiveEquipmentTypeRepository();
        $this->MapLinkRepository = new MapLinkRepository();
        $this->PoleRepository = new PoleRepository();
        $this->PositionRepository = new PositionRepository();
        $this->RackRepository = new RackRepository();
        $this->SiteRepository = new SiteRepository();
        $this->TableRepository = new TableRepository();
        $this->TubeTypeRepository = new TubeTypeRepository();
        $this->TrencheTypesRepository = new TrencheTypesRepository();
        $this->PortRepository = new PortRepository();
        $this->SimpleService = new SimpleService();
    }

    public function index()
    {
        return view('meganet.module.mapas.index', [
            'notifications' => $this->userNotification()
        ]);
    }

    public function getForm(Request $request)
    {
        $lastRecord = $request->object_type::orderBy('id', 'desc')->first();
        switch ($request->object_type) {
            case (Pole::class):
                $types = $this->PoleRepository->getDataEnum('type');
                $tensions = $this->PoleRepository->getDataEnum('tension');
                return view('meganet.module.mapas.objectForms.pole', compact('types', 'tensions', 'lastRecord'));
            case (Trench::class):
                $types = $this->TrencheTypesRepository->getAll();
                return view("meganet.module.mapas.objectForms.trenche", compact('types', 'lastRecord'));
            case (Box::class):
                $boxTypes = $this->BoxTypeRepository->getAll();
                return view('meganet.module.mapas.objectForms.box', compact('boxTypes', 'lastRecord'));
            case (Site::class):
                return view('meganet.module.mapas.objectForms.site');
            case (CutFiber::class):
                return view('meganet.module.mapas.objectForms.fiber_cut');
        }
    }

    public function objectCreate(Request $request)
    {
        $newPosition = $this->PositionRepository->getByPointMapProyectId(
            $request->latitude,
            $request->longitude,
            $request->map_proyect_id
        );

        if (!empty($newPosition)) {
            return response()->json([
                'res' => false,
                'message' => 'Esta ubicación ya esta cupada en este proyecto',
            ], 490);
        }

        return $this->SimpleService->simpleTransaction(function () use ($request) {
            $table = $this->TableRepository->findByString($request->object_type);

            $repository = App::make($table->repository_class);

            $object = $repository->create($request->all());

            if ($object::class === Box::class)
                $repository->createComponents($object);

            $this->PositionRepository->create([
                "point" => new Point($request->latitude, $request->longitude),
                "positionable_id" => $object->id,
                "positionable_type" => $object::class,
            ]);

            if (!empty($request->map_link_id)) {
                $this->MapLinkRepository->insetObject(
                    $request->map_link_id,
                    $object->id,
                    $object::class
                );
            }
        });
    }

    public function getObject(Request $request)
    {
        $objects = $this->PositionRepository->getObjects($request->map_proyect_id);

        return response()->json([
            'res' => true,
            'data' => $objects,
        ], 200);
    }

    public function getInfoInfoWindow(Request $request)
    {
        $position = $this->PositionRepository->find($request->id);

        return response()->json([
            'res' => true,
            'view' => view('meganet.module.mapas.InfoWindows.site', compact('position'))->render(),
        ], 200);
    }

    public function getDataForm(Request $request)
    {
        $position = $this->PositionRepository->getByPointMapProyectId($request->longitude, $request->latitude, $request->map_proyect_id);
        $object = $position->positionable;
        $table = $this->TableRepository->findByModelClass($object::class);

        return response()->json([
            'res' => true,
            'type' => $table->type,
            'view' => $this->MapService->getDataForm($object, $table),
        ], 200);
    }

    public function getCatalogView(Request $request)
    {
        return response()->json([
            'res' => true,
            'type' => $request->type,
            'view' => $this->MapService->getCatalogForm($request->catalog_view),
        ], 200);
    }

    public function getPolesBySite(Request $request)
    {
        return $this->PoleRepository->getBySiteIdForDatatable($request->site_id);
    }

    public function updatePosition(Request $request)
    {
        try {

            $newPosition = $this->PositionRepository->getByPointMapProyectId(
                $request->end_latitude,
                $request->end_longitude,
                $request->map_proyect_id
            );

            if (!empty($newPosition)) {
                return response()->json([
                    'res' => false,
                    'message' => 'Esta ubicación ya esta cupada en este proyecto',
                ], 490);
            }

            DB::beginTransaction();

            $position = $this->PositionRepository->getByPointMapProyectId(
                $request->start_longitude,
                $request->start_latitude,
                $request->map_proyect_id
            );

            $this->PositionRepository->update($position, [
                "point" => new Point($request->end_latitude, $request->end_longitude),
            ]);

            DB::commit();
            return response()->json([
                'res' => true,
                'message' => 'Guardado',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'res' => false,
                'message' => 'Ha ocurrido un error',
            ], 490);
        }
    }

    public function getDataFormById(Request $request)
    {
        $table = $this->TableRepository->findByString($request->type);

        $repository = App::make($table->repository_class);

        if ($request->type === "caja") {
            $object = $repository->findByBoxInputId($request->id);
        } else {
            $object = $repository->find($request->id);
        }

        return response()->json([
            'res' => true,
            'type' => $table->type,
            'view' => $this->MapService->getDataForm($object, $table)
        ], 200);
    }

    public function getAssingListPole(Request $request)
    {
        $data = $this->PoleRepository->SearchForSelect($request->text, $request->with_out_ids, $request->page);
        return response()->json($data, 200);
    }

    public function getListMapLinks(Request $request)
    {
        $mapLinks = $this->MapLinkRepository->getMapLinksFiltered($request->map_proyect_id, $request->visible_routes);

        return response()->json([
            'res' => true,
            'data' => $mapLinks,
        ], 200);
    }

    public function objectListForSelectByType(Request $request)
    {
        $objectTable = $this->TableRepository->findByType($request->object["type"]);

        $table = $this->TableRepository->findByType($request->type);

        $repository = App::make($table->repository_class);

        $mapLinks = $this->MapLinkRepository->getMapLinksByObject($request->object["id"], $objectTable->model_class);

        $withOutIds = [];

        foreach ($mapLinks as $mapLink) {
            if ($mapLink->input_type === $table->model_class)
                array_push($withOutIds, $mapLink->input_id);

            if ($mapLink->output_type === $table->model_class)
                array_push($withOutIds, $mapLink->output_id);
        }

        $data = $repository->SearchForSelect($request->text, $withOutIds, $request->page);

        return response()->json($data, 200);
    }

    public function assingPoint(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function () use ($request) {
            $point = $this->PointRepository->find(intval($request->point_id));

            $mapLinks = $this->MapLinkRepository->getMapLinksByObject($point->id, ModelsPoint::class);

            $table = $this->TableRepository->findByModelClass($request->object_type);

            $repository = App::make($table->repository_class);

            $object = $repository->create($request->all());

            $this->PositionRepository->update($point->position, [
                "point" => new Point($request->latitude, $request->longitude),
                "positionable_id" => $object->id,
                "positionable_type" => $object::class,
            ]);

            foreach ($mapLinks as $mapLink) {
                $data = [];
                if ($mapLink->input_id === $point->id && $mapLink->input_type === ModelsPoint::class) {
                    $data["input_id"] = $object->id;
                    $data["input_type"] = $object::class;
                }

                if ($mapLink->output_id === $point->id && $mapLink->output_type === ModelsPoint::class) {
                    $data["output_id"] = $object->id;
                    $data["output_type"] = $object::class;
                }

                $this->MapLinkRepository->update($mapLink, $data);
            }

            $point->ports->map(function ($port) use ($object) {
                $this->PortRepository->update($port, [
                    'portable_id' => $object->id,
                    'portable_type' => $object::class,
                ]);
            });

            if ($object::class === Box::class) {
                $type = $object->type;

                for ($i = 1; $i <= $type->inputs; $i++) {
                    $this->BoxInputRepository->create([
                        'number' => $i,
                        'box_id' => $object->id,
                    ]);
                }
            }

            $this->PointRepository->delete($point);

            return [
                'res' => true,
                'message' => 'Guardado',
                'type' => $table->type,
                'view' => $this->MapService->getDataForm($object, $table)
            ];
        });
    }

    public function searchInputsOrPorts(Request $request)
    {
        $object = $request->object;

        return response()->json($this->PortRepository->SearchForSelect(
            $request->text,
            [$request->id],
            $request->page,
            function ($query) use ($object) {
                $repository = new TableRepository();
                $table = $repository->findByType($object["type"]);

                $query->select(
                    "ports.id",
                    DB::raw("CONCAT(ports.number, ' (', ports.type, ')') AS text")
                )
                    ->leftJoin('equipment_links', function ($join) {
                        $join->on('equipment_links.input_id', 'ports.id')
                            ->orWhereColumn('equipment_links.output_id', 'ports.id');
                    })
                    ->where('portable_type', '=', $table->model_class)
                    ->where('portable_id', '=', $object["id"])
                    ->whereNull('equipment_links.id');
            }
        ), 200);
    }

    public function setSessionPosition(Request $request)
    {
        Session::put('latitudeSession', $request->latitude);
        Session::put('longitudeSession', $request->longitude);

        return response()->json(['res' => true], 200);
    }
    /*
    |----------------------------------------------------------------------------
    |  JSTHREE
    |----------------------------------------------------------------------------
    */
    public function getListMapthree(Request $request)
    {
        $mapLinks = $this->MapProyectRepository->getMapPoints($request->map_proyect_id);

        return response()->json([
            'res' => true,
            'data' => $mapLinks,
        ], 200);
    }

    function fiberCutStore(Request $request)
    {
        $port = Port::find($request->input_id);
        $previousPort = null;
        $calculatedDistance = 0;


        if ($port->links()->count() <> 1)
            throw new Exception("Este puerto no tiene conexiones o esta mas conectado");

        while ($calculatedDistance < $request->meter) {

            $nextPort = $this->nextPort($port, $previousPort);

            $object = $this->positionableObject($port);
            $nextObject = $this->positionableObject($nextPort);

            if ($nextObject !== $object) {
                $calculatedDistance += (new PositionRepository)->distanceBetweenTwoPoints(
                    positionFirstId: $object->position->id,
                    positionSecondId: $nextObject->position->id
                );
            }

            $previousPort = $port;

            if ($calculatedDistance < $request->meter)
                $port = $nextPort;
        }

        $position1 = $object->position->id;
        $position2 = $nextObject->position->id;

        $distanceBetweenLastPoints = (new PositionRepository)->distanceBetweenTwoPoints(
            positionFirstId: $object->position->id,
            positionSecondId: $nextObject->position->id
        );

        $missingDistance = $distanceBetweenLastPoints - ($calculatedDistance - $request->meter);

        $point3 = DB::select("call calculatePoint($position1, $position2, $missingDistance)");

        return $this->SimpleService->simpleTransaction(function () use ($request, $point3) {
            $cutFiber = $this->CutFiberRepository->create($request->all());

            $this->PositionRepository->create([
                "point" => new Point($point3[0]->Y, $point3[0]->X),
                "positionable_id" => $cutFiber->id,
                "positionable_type" => $cutFiber::class,
            ]);
        });
    }

    function fiberCutUpdate(Request $request)
    {
        $cutFiber = $this->CutFiberRepository->find($request->object_id);
        $port = $cutFiber->passiveEquipment->ports->where('type', 'fibra')->first();
        $previousPort = null;
        $calculatedDistance = 0;

        if ($port->links()->count() <> 1)
            throw new Exception("Este puerto no tiene conexiones o esta mas conectado");

        while ($calculatedDistance < $request->meter) {

            $nextPort = $this->nextPort($port, $previousPort);

            $object = $this->positionableObject($port);
            $nextObject = $this->positionableObject($nextPort);

            if ($nextObject !== $object) {
                $calculatedDistance += (new PositionRepository)->distanceBetweenTwoPoints(
                    positionFirstId: $object->position->id,
                    positionSecondId: $nextObject->position->id
                );
            }

            $previousPort = $port;

            if ($calculatedDistance < $request->meter)
                $port = $nextPort;
        }

        $position1 = $object->position->id;
        $position2 = $nextObject->position->id;

        $distanceBetweenLastPoints = (new PositionRepository)->distanceBetweenTwoPoints(
            positionFirstId: $object->position->id,
            positionSecondId: $nextObject->position->id
        );

        $missingDistance = $distanceBetweenLastPoints - ($calculatedDistance - $request->meter);

        $point3 = DB::select("call calculatePoint($position1, $position2, $missingDistance)");

        return $this->SimpleService->simpleTransaction(function () use ($request, $point3, $cutFiber) {

            $this->CutFiberRepository->update($cutFiber, $request->all());

            $position = $this->PositionRepository->getByObjectIdAndType($cutFiber->id, $cutFiber::class);

            $this->PositionRepository->update($position, [
                "point" => new Point($point3[0]->Y, $point3[0]->X)
            ]);
        });
    }

    function fiberCutDestroy(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function () use ($request) {
            $cutFiber = $this->CutFiberRepository->find($request->id);
            $this->PositionRepository->delete($cutFiber->position);
            $this->CutFiberRepository->delete($cutFiber);
        });
    }

    public function nextPort(Port $port, ?Port $previousPort)
    {
        $ports = $port->linkedPorts();

        if ($ports->isEmpty())
            return null;

        if ($ports->count() === 1)
            return $ports->first();

        if ($ports->count() === 2) {
            return $ports->where("id", "!=", $previousPort->id)->first();
        }

        throw new Exception("se encontro un puero mal conectado id - $port->id");
    }

    public function positionableObject(Port $port)
    {
        $object = $port->portable;


        if ($object::class === ActiveEquipment::class) {
            return $this->SiteRepository->findByEquipment($object->id, "active_equipments");
        }

        if ($object::class === BoxInput::class) {
            return $this->BoxRepository->findByBoxInputId($object->id);
        }

        if ($object::class === PassiveEquipment::class) {
            return $this->SiteRepository->findByEquipment($object->id, "passive_equipments");
        }

        if ($object::class === Splitter::class) {
            return $this->BoxRepository->findBySplitter($object->id);
        }

        if ($object::class === Tray::class) {
            return $this->BoxRepository->findByTray($object->id);
        }

        return $object;
    }
}
