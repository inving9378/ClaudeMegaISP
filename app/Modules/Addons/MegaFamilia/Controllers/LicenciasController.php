<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalLicense;
use App\Modules\Addons\MegaFamilia\Models\ParentalPlan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LicenciasController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::licencias.index');
    }

    public function data(): JsonResponse
    {
        $byPlan = ParentalLicense::query()
            ->join('parental_plans', 'parental_plans.id', '=', 'parental_licenses.plan_id')
            ->select(
                'parental_plans.id', 'parental_plans.name',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN parental_licenses.status='active' THEN 1 ELSE 0 END) as active"),
                DB::raw("SUM(CASE WHEN parental_licenses.status='suspended' THEN 1 ELSE 0 END) as suspended"),
                DB::raw("SUM(CASE WHEN parental_licenses.status='expired' THEN 1 ELSE 0 END) as expired")
            )
            ->groupBy('parental_plans.id', 'parental_plans.name')
            ->get();

        $list = ParentalLicense::with(['account.client:id,name,email', 'plan:id,name'])
            ->orderByDesc('id')->paginate(25);

        return response()->json(['by_plan' => $byPlan, 'list' => $list]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => 'required|exists:parental_accounts,id',
            'plan_id' => 'required|exists:parental_plans,id',
            'expires_at' => 'nullable|date',
        ]);
        $data['status'] = 'active';
        $data['activated_at'] = Carbon::now();
        $license = ParentalLicense::create($data);
        return response()->json(['success' => true, 'license' => $license]);
    }

    public function renew(int $id): JsonResponse
    {
        $l = ParentalLicense::findOrFail($id);
        $base = $l->expires_at && $l->expires_at->isFuture() ? $l->expires_at : Carbon::now();
        $l->update([
            'status' => 'active',
            'expires_at' => $base->copy()->addMonth(),
            'suspended_at' => null,
            'suspended_reason' => null,
        ]);
        return response()->json(['success' => true, 'license' => $l]);
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $l = ParentalLicense::findOrFail($id);
        $l->update([
            'status' => 'suspended',
            'suspended_at' => Carbon::now(),
            'suspended_reason' => $request->input('reason'),
        ]);
        return response()->json(['success' => true]);
    }
}
