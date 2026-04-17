<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\TableRepository;
use Illuminate\Http\Request;

class TableController extends Controller
{
    protected $TableRepository;
    protected $SimpleService;

    public function __construct()
    {
        $this->TableRepository = new TableRepository();
    }

    public function getByName(Request $request)
    {
        $table = $this->TableRepository->getByName($request->name);
        return response()->json([
            'res' => true,
            'table' => $table,
            'index_route'=>route("maps.$table->type.index"),
            'show_route'=>route("maps.getDataFormById"),
            'store_route'=>route("maps.$table->type.store")
        ], 200);
    }
}
