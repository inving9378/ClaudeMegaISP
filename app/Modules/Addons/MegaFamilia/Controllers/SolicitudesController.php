<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolicitudesController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::solicitudes.index');
    }

    public function data(Request $request): JsonResponse
    {
        $q = ParentalRequest::with(['profile:id,name', 'device:id,name'])
            ->when($request->status, fn ($qq, $v) => $qq->where('status', $v), fn ($qq) => $qq->where('status', 'pending'))
            ->orderByDesc('id');

        return response()->json($q->paginate(25));
    }

    public function approve(int $id): JsonResponse
    {
        $r = ParentalRequest::findOrFail($id);
        $r->update(['status' => 'approved', 'responded_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function reject(int $id): JsonResponse
    {
        $r = ParentalRequest::findOrFail($id);
        $r->update(['status' => 'rejected', 'responded_at' => now()]);
        return response()->json(['success' => true]);
    }
}
