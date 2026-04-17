<?php

namespace App\Http\Controllers\Module\OLTs;

use App\Http\Controllers\Controller;
use App\Http\Traits\DatatableCoreTrait;
use App\Models\OltTypeONU;
use App\Services\OLTsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class OLTsTypeOnusController extends Controller
{
    use DatatableCoreTrait;

    private $model;
    protected $oltService;

    public function __construct()
    {
        $this->model = OltTypeONU::class;
        $this->oltService = new OLTsService();
    }

    public function index(Request $request)
    {
        if ($request->force) {
            Artisan::call('smartolt:sync-inventory', ['--only' => 'type-onus']);
        }
        $columns =  $request->columns;
        $order = $request->sortBy ?? $columns[0];
        $dir = $request->descending ? 'DESC' : 'ASC';
        $mapping = $this->getColumnMapping();
        $query  = $this->getGeneralQuery($columns, $mapping);
        $query = $this->applySearch($query, $request->search ?? null, $columns, $mapping);
        $query = $this->applySorting($query, $order, $dir, $mapping);
        $objects = $query->paginate(isset($request->rowsPerPage) ? $request->rowsPerPage : 20, ['*'], 'page', isset($request->page) ? $request->page : null);
        return response()->json(
            [
                'objects' => $objects->items(),
                'total' => $objects->total()
            ]
        );
    }

    protected function getBaseColumnsByTable()
    {
        return [
            'olt_type_onus' => [
                'name' => ['searchable' => true],
                'pon_type' => ['searchable' => true],
                'capability' => ['searchable' => true],
                'ethernet_ports' => ['searchable' => true],
                'wifi_ports' => ['searchable' => true],
                'voip_ports' => ['searchable' => true],
                'catv' => ['searchable' => true],
                'allow_custom_profiles' => ['searchable' => true],
                'last_synced_at' => ['searchable' => false]
            ]
        ];
    }

    public function store(Request $request)
    {
        $response = $this->oltService->addTypeOnu($request->all());
        return response()->json($response);
    }
}
