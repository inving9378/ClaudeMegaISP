<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispositivosController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::dispositivos.index');
    }

    public function data(Request $request): JsonResponse
    {
        $q = ParentalDevice::with(['profile:id,name', 'account:id,client_id'])
            ->when($request->status, fn ($qq, $v) => $qq->where('status', $v))
            ->when($request->os, fn ($qq, $v) => $qq->where('os', $v))
            ->orderByDesc('last_seen_at');
        return response()->json($q->paginate(25));
    }

    public function show(int $id): JsonResponse
    {
        $d = ParentalDevice::with(['profile', 'account.client', 'locations' => fn ($q) => $q->latest('recorded_at')->limit(50)])
            ->findOrFail($id);
        return response()->json($d);
    }
}
