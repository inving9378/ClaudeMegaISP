<?php

namespace App\Http\Controllers\Module\OLTs;

use App\Http\Controllers\Controller;
use App\Http\Traits\DatatableCoreTrait;
use App\Models\OltSpeedProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class OLTsProfilesController extends Controller
{

    use DatatableCoreTrait;

    private $model;

    public function __construct()
    {
        $this->model = OltSpeedProfile::class;
    }

    public function index(Request $request)
    {
        if ($request->force) {
            Artisan::call('smartolt:sync-inventory', ['--only' => 'profiles']);
        }
        $columns =  $request->columns;
        $order = $request->sortBy ?? $columns[0];
        $dir = $request->descending ? 'DESC' : 'ASC';
        $mapping = $this->getColumnMapping();
        $query  = $this->getGeneralQuery($columns, $mapping);
        $query->where('direction', $request->direction);
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
            'olt_speed_profiles' => [
                'name' => ['searchable' => true],
                'speed' => ['searchable' => true],
                'direction' => ['searchable' => true],
                'type' => ['searchable' => true]
            ]
        ];
    }
}
