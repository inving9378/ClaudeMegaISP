<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::auditoria.index');
    }

    public function data(Request $request): JsonResponse
    {
        $q = ParentalEvent::query()
            ->when($request->action, fn ($qq, $v) => $qq->where('action', $v))
            ->when($request->account_id, fn ($qq, $v) => $qq->where('account_id', $v))
            ->when($request->date_from, fn ($qq, $v) => $qq->where('created_at', '>=', $v))
            ->when($request->date_to, fn ($qq, $v) => $qq->where('created_at', '<=', $v))
            ->orderByDesc('id');

        return response()->json($q->paginate((int) $request->input('per_page', 30)));
    }
}
