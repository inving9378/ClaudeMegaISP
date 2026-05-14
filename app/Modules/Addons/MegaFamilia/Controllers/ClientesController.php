<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientesController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::clientes.index');
    }

    public function data(Request $request): JsonResponse
    {
        $q = ParentalAccount::with(['plan:id,name,slug', 'client:id,name,email'])
            ->when($request->plan_id, fn ($qq, $v) => $qq->where('plan_id', $v))
            ->when($request->status, fn ($qq, $v) => $qq->where('status', $v))
            ->when($request->search, function ($qq, $v) {
                $qq->whereHas('client', fn ($c) => $c->where('name', 'like', "%$v%")
                    ->orWhere('email', 'like', "%$v%"));
            })
            ->orderByDesc('id');

        return response()->json($q->paginate((int) $request->input('per_page', 20)));
    }

    public function show(int $id): JsonResponse
    {
        $account = ParentalAccount::with([
            'plan', 'client', 'profiles.devices', 'license', 'alerts' => fn ($q) => $q->latest()->limit(20),
        ])->findOrFail($id);
        return response()->json($account);
    }

    public function activate(int $id): JsonResponse
    {
        $a = ParentalAccount::findOrFail($id);
        $a->update(['status' => 'active']);
        return response()->json(['success' => true]);
    }

    public function suspend(int $id): JsonResponse
    {
        $a = ParentalAccount::findOrFail($id);
        $a->update(['status' => 'suspended']);
        return response()->json(['success' => true]);
    }
}
