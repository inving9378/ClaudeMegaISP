<?php

namespace App\Http\Controllers\Module\OLTs;

use App\Http\Controllers\Controller;
use App\Http\Traits\DatatableCoreTrait;
use App\Models\OltUplinkPort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class OLTsUplinkPortsController extends Controller
{
    use DatatableCoreTrait;

    private $model;

    public function __construct()
    {
        $this->model = OltUplinkPort::class;
    }

    public function index(Request $request, $id)
    {
        if ($request->force) {
            Artisan::call('smartolt:sync-inventory', [
                '--only' => 'uplink-ports',
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
            'olt_uplink_ports' => [
                'name' => ['searchable' => true],
                'type' => ['searchable' => true],
                'mode' => ['searchable' => true],
                'admin_status' => ['searchable' => true],
                'status' => ['searchable' => true],
                'vlan_tag' => ['searchable' => true],
                'negotiation_auto' => ['searchable' => true],
                'mtu' => ['searchable' => true],
                'wavelength' => ['searchable' => true],
                'temperature' => ['searchable' => true],
                'pvid' => ['searchable' => true],
                'description' => ['searchable' => true],
                'last_synced_at' => ['searchable' => false]
            ]
        ];
    }
}
