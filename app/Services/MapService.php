<?php


namespace App\Services;

use App\Models\Color;
use App\Models\Port;
use App\Models\Table;
use App\Repositories\ActiveEquipmentRepository;
use App\Repositories\ActiveEquipmentTypeRepository;
use App\Repositories\BoxInputRepository;
use App\Repositories\BoxRepository;
use App\Repositories\BoxTypeRepository;
use App\Repositories\BrandRepository;
use App\Repositories\CardRepository;
use App\Repositories\EquipmentLinkRepository;
use App\Repositories\MapLinkRepository;
use App\Repositories\MapProyectRepository;
use App\Repositories\MapRouteRepository;
use App\Repositories\PassiveEquipmentRepository;
use App\Repositories\PassiveEquipmentTypeRepository;
use App\Repositories\PointAccessoryRepository;
use App\Repositories\PoleAccessoryRepository;
use App\Repositories\PoleRepository;
use App\Repositories\PortRepository;
use App\Repositories\PositionRepository;
use App\Repositories\TableRepository;
use App\Repositories\TransceiverRepository;
use App\Repositories\TrencheTypesRepository;
use App\Repositories\TubeRepository;
use App\Repositories\TubeTypeRepository;
use Illuminate\Support\Facades\App;

class MapService
{
    protected $ActiveEquipmentRepository;
    protected $ActiveEquipmentTypeRepository;
    protected $BoxRepository;
    protected $BoxTypeRepository;
    protected $BoxInputRepository;
    protected $BrandRepository;
    protected $CardRepository;
    protected $MapProyectRepository;
    protected $MapLinkRepository;
    protected $MapRouteRepository;
    protected $PassiveEquipmentTypeRepository;
    protected $PassiveEquipmentRepository;
    protected $PoleRepository;
    protected $PoleAccessoryRepository;
    protected $PointAccessoryRepository;
    protected $PositionRepository;
    protected $PortRepository;
    protected $TableRepository;
    protected $TransceiverRepository;
    protected $TrencheTypesRepository;
    protected $TrenchTypeRepository;
    protected $TubeTypeRepository;
    protected $EquipmentLinkRepository;

    public function __construct()
    {
        $this->ActiveEquipmentRepository = new ActiveEquipmentRepository();
        $this->ActiveEquipmentTypeRepository = new ActiveEquipmentTypeRepository();
        $this->BoxRepository = new BoxRepository();
        $this->BoxTypeRepository = new BoxTypeRepository();
        $this->BoxInputRepository = new BoxInputRepository();
        $this->BrandRepository = new BrandRepository();
        $this->CardRepository = new CardRepository();
        $this->MapLinkRepository = new MapLinkRepository();
        $this->MapProyectRepository = new MapProyectRepository();
        $this->MapRouteRepository =new MapRouteRepository();
        $this->PassiveEquipmentTypeRepository = new PassiveEquipmentTypeRepository();
        $this->PassiveEquipmentRepository = new PassiveEquipmentRepository();
        $this->PointAccessoryRepository = new PointAccessoryRepository;
        $this->PoleAccessoryRepository = new PoleAccessoryRepository();
        $this->PoleRepository = new PoleRepository();
        $this->PositionRepository = new PositionRepository();
        $this->PortRepository = new PortRepository();
        $this->TableRepository = new TableRepository();
        $this->TransceiverRepository = new TransceiverRepository();
        $this->TrencheTypesRepository = new TrencheTypesRepository();
        $this->TubeTypeRepository = new TubeTypeRepository();
        $this->EquipmentLinkRepository = new EquipmentLinkRepository;
    }

    public function getDataForm($object, Table $objectTable)
    {
        $mapRoutes = $this->MapRouteRepository->getByObject($object);
        $colors = Color::all();

        $positionableObjects = $this->TableRepository->getHasPosition(true);
        $mapProyects = $this->MapProyectRepository->getAll();

        $centerMapLatitude = config('services.cache.center_map_latitude');
        $centerMapLongitude = config('services.cache.center_map_longitude');

        $types = $this->PoleRepository->getDataEnum('type');
        $tensions = $this->PoleRepository->getDataEnum('tension');

        $passiveEquipmentOptions = $this->PassiveEquipmentTypeRepository->getAll();
        $activeEquipmentOptions = $this->ActiveEquipmentTypeRepository->getAll();
        $activeEquipmentTypes = $this->ActiveEquipmentTypeRepository->getDataEnum('type');
        $passiveEquipmentTypes = $this->PassiveEquipmentTypeRepository->getDataEnum('type');

        $objectLinked = $this->getObjectLinked($object);
        $equipmentLinked =  empty($objectLinked)?null:$objectLinked->portable;
        $objectLinkedTable = empty($objectLinked)?null:$objectLinked->infoTable();

        $boxTypes = $this->BoxTypeRepository->getAll();
        $cartTypes = $this->CardRepository->getDataEnum('type');
        $portTypes = $this->PortRepository->getDataEnum('type');
        $pointAccessoryTypes = $this->PointAccessoryRepository->getDataEnum('name');
        $poleAccessoryTypes = $this->PoleAccessoryRepository->getDataEnum('name');
        $transceiverTypes = $this->TransceiverRepository->getDataEnum('type');
        $trenchTypes = $this->TrencheTypesRepository->getAll();
        $otherPort = $this->PortRepository->findOtherPort($object->portable_id, $object->portable_type, $object->number, $object->id);

        $data = [
            'object',
            'mapProyects',
            'positionableObjects',
            'objectTable',
            'centerMapLatitude',
            'centerMapLongitude',
            'types',
            'tensions',
            "passiveEquipmentOptions",
            "activeEquipmentOptions",
            "objectLinked",
            "objectLinkedTable",
            "boxTypes",
            "cartTypes",
            "portTypes",
            "pointAccessoryTypes",
            "transceiverTypes",
            "otherPort",
            "poleAccessoryTypes",
            "trenchTypes",
            "mapRoutes",
            "colors",
            "equipmentLinked",
            'activeEquipmentTypes',
            'passiveEquipmentTypes'
        ];

        return view('meganet.module.mapas.data.'.$objectTable->type, compact($data))->render();
    }

    public function getCatalogForm($object)
    {
        $mapProyects = $this->MapProyectRepository->getAll();
        $centerMapLatitude = config('services.cache.center_map_latitude');
        $centerMapLongitude = config('services.cache.center_map_longitude');
        $brands = $this->BrandRepository->getModel()->get();
        $tubeTypes = $this->TubeTypeRepository->getDataEnum('type');
        $activeEquipmentTypes = $this->ActiveEquipmentTypeRepository->getDataEnum('type');
        $passiveEquipmentTypes = $this->PassiveEquipmentTypeRepository->getDataEnum('type');

        $data = [
            'mapProyects',
            'centerMapLatitude',
            'centerMapLongitude',
            'brands',
            'tubeTypes',
            'activeEquipmentTypes',
            'passiveEquipmentTypes'
        ];

        return view('meganet.module.mapas.'.$object, compact($data))->render();
    }

    public function otherPortView(?int $objectId, ?string $objectType, ?int $number, int $portId)
    {
        $object = $this->PortRepository->findOtherPort($objectId, $objectType, $number, $portId);

        if(empty($object))
            return null;

        $portTypes = $this->PortRepository->getDataEnum('type');
        $objectTable = $this->TableRepository->findByModelClass($object::class);

        $view = view('meganet.module.mapas.auxViews.port', compact([
            "object",
            "portTypes",
            "objectTable"
        ]));

        // $view = substr($view, 0, -2);

        return $view;
    }

    public function getObjectLinked($object)
    {
        $repository = new EquipmentLinkRepository();

        $equipmentLink = $repository->findByData($object->id);

        if(empty($equipmentLink))
            return null;

        if($equipmentLink->input_id === $object->id)
            return $equipmentLink->outputObject;

        return $equipmentLink->inputObject;
    }

    public function destroyObject($object){

        $this->EquipmentLinkRepository->destroyByObject($object);
        $this->MapLinkRepository->destroyByObject($object);
        $this->ActiveEquipmentRepository->destroyByObject($object);
        $this->PassiveEquipmentRepository->destroyByObject($object);
        $this->BoxInputRepository->destroyByObject($object);
        $this->CardRepository->destroyByObject($object);
        $this->PortRepository->destroyByObject($object);
        $this->PositionRepository->destroyByObject($object);
        $this->BoxRepository->destroyByObject($object);
        $this->MapRouteRepository->destroyByObject($object);

        $table = $this->TableRepository->findByModelClass($object::class);
        $repository = App::make($table->repository_class);
        $repository->delete($object);
    }
}
