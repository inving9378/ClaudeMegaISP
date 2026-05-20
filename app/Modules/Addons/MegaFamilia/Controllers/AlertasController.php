<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertasController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::alertas.index');
    }

    public function data(Request $request): JsonResponse
    {
        $q = ParentalAlert::with(['profile:id,name', 'device:id,name', 'account:id,client_id'])
            ->when($request->type, fn ($qq, $v) => $qq->where('type', $v))
            ->when($request->unread === 'true', fn ($qq) => $qq->whereNull('read_at'))
            ->orderByDesc('id');

        return response()->json($q->paginate((int) $request->input('per_page', 25)));
    }

    public function markRead(int $id): JsonResponse
    {
        $a = ParentalAlert::findOrFail($id);
        $a->update(['read_at' => now()]);
        return response()->json(['success' => true]);
    }
}
