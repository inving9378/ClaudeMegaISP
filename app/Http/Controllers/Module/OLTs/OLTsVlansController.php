<?php

namespace App\Http\Controllers\Module\OLTs;

use App\Http\Controllers\Controller;
use App\Http\Traits\DatatableCoreTrait;
use App\Models\OltVlan;
use App\Services\OLTsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class OLTsVlansController extends Controller
{
    use DatatableCoreTrait;

    private $model;
    protected $oltService;

    public function __construct()
    {
        $this->model = OltVlan::class;
        $this->oltService = new OLTsService();
    }

    public function index(Request $request, $id)
    {
        if ($request->force) {
            Artisan::call('smartolt:sync-inventory', [
                '--only' => 'vlans',
                'olt' => $id
            ]);
        }
        $columns =  $request->columns;
        $order = $request->sortBy ?? $columns[0];
        $dir = $request->descending ? 'DESC' : 'ASC';
        $mapping = $this->getColumnMapping();
        $query  = $this->getGeneralQuery($columns, $mapping);
        $query->where('olt_id', $id);
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
            'olt_vlans' => [
                'vlan' => ['searchable' => true],
                'scope' => ['searchable' => true],
                'description' => ['searchable' => true],
                'last_synced_at' => ['searchable' => false]
            ]
        ];
    }

    public function store(Request $request, $id)
    {
        $response = $this->oltService->addVlan($id, $request->all());
        return response()->json($response);
    }
}
