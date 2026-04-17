<?php

namespace App\Http\Controllers\Module\OLTs;

use App\Http\Controllers\Controller;
use App\Http\Traits\DatatableCoreTrait;
use App\Models\OltOdb;
use App\Services\OLTsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class OLTsODBsController extends Controller
{
    use DatatableCoreTrait;

    private $model;

    protected $oltService;

    public function __construct()
    {
        $this->model = OltOdb::class;
        $this->oltService = new OLTsService();
    }

    public function index(Request $request)
    {
        if ($request->force) {
            Artisan::call('smartolt:sync-inventory', ['--only' => 'odbs']);
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
            'olt_odbs' => [
                'name' => ['searchable' => true],
                'latitude' => ['searchable' => true],
                'longitude' => ['searchable' => true],
                'zone_name' => ['searchable' => true],
                'last_synced_at' => ['searchable' => false]
            ]
        ];
    }

    public function store(Request $request)
    {
        $response = $this->oltService->addOdb($request->all());
        return response()->json($response);
    }
}
