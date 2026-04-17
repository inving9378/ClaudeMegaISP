<?php

namespace App\Http\Controllers\Module\OLTs;

use App\Http\Controllers\Controller;
use App\Http\Traits\DatatableCoreTrait;
use App\Models\OltZone;
use App\Services\OLTsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class OLTsZonesController extends Controller
{
    use DatatableCoreTrait;

    protected $oltService;

    public function __construct()
    {
        $this->oltService = new OLTsService();
    }

    public function index(Request $request)
    {
        if ($request->force) {
            Artisan::call('smartolt:sync-inventory', ['--only' => 'zones']);
        }
        $query = OltZone::query();
        if (isset($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if (isset($request->sortBy)) {
            $query->orderBy($request->sortBy, $request->descending ? 'DESC' : 'ASC');
        }
        $objects = $query->paginate(isset($request->rowsPerPage) ? $request->rowsPerPage : 20, ['*'], 'page', isset($request->page) ? $request->page : null);
        return response()->json([
            'objects' => $objects->items(),
            'total' => $objects->total()
        ]);
    }

    public function store(Request $request)
    {
        $response = $this->oltService->addZone($request->only('zone'));
        return response()->json($response);
    }
}
