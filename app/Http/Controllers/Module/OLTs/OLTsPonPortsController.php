<?php

namespace App\Http\Controllers\Module\OLTs;

use App\Http\Controllers\Controller;
use App\Http\Traits\DatatableCoreTrait;
use App\Models\OltPonPort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class OLTsPonPortsController extends Controller
{
    use DatatableCoreTrait;

    private $model;

    public function __construct()
    {
        $this->model = OltPonPort::class;
    }

    public function index(Request $request, $id)
    {
        if ($request->force) {
            Artisan::call('smartolt:sync-inventory', [
                '--only' => 'pon-ports',
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
            'olt_pon_ports' => [
                'board' => ['searchable' => true],
                'pon_port' => ['searchable' => true],
                'pon_type' => ['searchable' => true],
                'admin_status' => ['searchable' => true],
                'operational_status' => ['searchable' => true],
                'onus_count' => ['searchable' => true],
                'online_onus_count' => ['searchable' => true],
                'average_signal' => ['searchable' => true],
                'min_range' => ['searchable' => true],
                'max_range' => ['searchable' => true],
                'tx_power' => ['searchable' => true],
                'description' => ['searchable' => true],
                'last_synced_at' => ['searchable' => false]
            ]
        ];
    }
}
