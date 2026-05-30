<?php

namespace App\Modules\Addons\Demo\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DemoController extends Controller
{
    public function index(): JsonResponse
    {
        $items = DB::table('demo_items')->get();
        return response()->json(['items' => $items]);
    }
}
