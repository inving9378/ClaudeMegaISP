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
        $q = ParentalAccount::query()
            ->with(['plan:id,name,slug', 'user:id,name,email'])
            ->withCount('devices')
            ->when($request->plan_id, fn ($qq, $v) => $qq->where('plan_id', $v))
            ->when($request->status, fn ($qq, $v) => $qq->where('status', $v))
            ->when($request->search, function ($qq, $v) {
                $qq->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$v}%")
                    ->orWhere('email', 'like', "%{$v}%"));
            })
            ->orderByDesc('id');

        return response()->json($q->paginate((int) $request->input('per_page', 20)));
    }

    public function show(int $id): JsonResponse
    {
        $account = ParentalAccount::with([
            'plan',
            'user:id,name,email',
            'clientIsp:id,fecha_corte,fecha_pago,fecha_suspension',
            'clientIsp.user',
            'profiles' => fn ($q) => $q->withCount('devices'),
            'profiles.devices:id,profile_id,name,os,status,last_seen_at',
            'license',
            'alerts' => fn ($q) => $q->latest()->limit(5),
        ])->findOrFail($id);

        return response()->json($account);
    }

    public function activate(int $id): JsonResponse
    {
        ParentalAccount::findOrFail($id)->update(['status' => 'active']);
        return response()->json(['success' => true]);
    }

    public function suspend(int $id): JsonResponse
    {
        ParentalAccount::findOrFail($id)->update(['status' => 'suspended']);
        return response()->json(['success' => true]);
    }

    public function cancel(int $id): JsonResponse
    {
        ParentalAccount::findOrFail($id)->update(['status' => 'cancelled']);
        return response()->json(['success' => true]);
    }

    public function changePlan(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => 'required|exists:parental_plans,id',
        ]);
        $account = ParentalAccount::findOrFail($id);
        $account->update(['plan_id' => $data['plan_id']]);
        return response()->json([
            'success' => true,
            'account' => $account->fresh(['plan:id,name,slug']),
        ]);
    }
}
