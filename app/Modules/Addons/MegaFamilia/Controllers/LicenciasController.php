<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
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

    public function data(Request $request): JsonResponse
    {
        $now    = Carbon::now();
        $cutoff = $now->copy()->addDays(30);

        $kpis = [
            'total'      => ParentalLicense::count(),
            'active'     => ParentalLicense::where('status', 'active')->count(),
            'expiring'   => ParentalLicense::where('status', 'active')
                                ->whereBetween('expires_at', [$now, $cutoff])->count(),
            'expired'    => ParentalLicense::where('status', 'expired')
                                ->orWhere(function ($q) use ($now) {
                                    $q->where('status', '!=', 'expired')->where('expires_at', '<', $now);
                                })->count(),
        ];

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

        $q = ParentalLicense::query()
            ->with(['account.user:id,name,email', 'plan:id,name,slug'])
            ->withCount(['account as devices_used' => function ($qq) {
                $qq->join('parental_devices', 'parental_devices.account_id', '=', 'parental_accounts.id');
            }])
            ->when($request->status, fn ($qq, $v) => $qq->where('parental_licenses.status', $v))
            ->when($request->plan_id, fn ($qq, $v) => $qq->where('parental_licenses.plan_id', $v))
            ->when($request->from, fn ($qq, $v) => $qq->whereDate('parental_licenses.expires_at', '>=', $v))
            ->when($request->to, fn ($qq, $v) => $qq->whereDate('parental_licenses.expires_at', '<=', $v))
            ->orderByDesc('id');

        $list = $q->paginate((int) $request->input('per_page', 25));

        return response()->json([
            'kpis'    => $kpis,
            'by_plan' => $byPlan,
            'list'    => $list,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => 'required|exists:parental_accounts,id',
            'plan_id'    => 'required|exists:parental_plans,id',
            'start_at'   => 'nullable|date',
            'months'     => 'nullable|integer|min:1|max:60',
            'expires_at' => 'nullable|date',
        ]);

        $start = isset($data['start_at']) ? Carbon::parse($data['start_at']) : Carbon::now();
        $expires = isset($data['expires_at'])
            ? Carbon::parse($data['expires_at'])
            : $start->copy()->addMonths((int) ($data['months'] ?? 1));

        $license = ParentalLicense::create([
            'account_id'   => $data['account_id'],
            'plan_id'      => $data['plan_id'],
            'status'       => 'active',
            'activated_at' => $start,
            'expires_at'   => $expires,
        ]);

        return response()->json(['success' => true, 'license' => $license]);
    }

    public function renew(Request $request, int $id): JsonResponse
    {
        $months = (int) $request->input('months', 1);
        $months = max(1, min(60, $months));

        $l = ParentalLicense::findOrFail($id);
        $base = $l->expires_at && $l->expires_at->isFuture() ? $l->expires_at : Carbon::now();

        $l->update([
            'status'           => 'active',
            'expires_at'       => $base->copy()->addMonths($months),
            'suspended_at'     => null,
            'suspended_reason' => null,
        ]);

        return response()->json(['success' => true, 'license' => $l]);
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $l = ParentalLicense::findOrFail($id);
        $l->update([
            'status'           => 'suspended',
            'suspended_at'     => Carbon::now(),
            'suspended_reason' => $request->input('reason'),
        ]);
        return response()->json(['success' => true]);
    }

    public function reactivate(int $id): JsonResponse
    {
        $l = ParentalLicense::findOrFail($id);
        $l->update([
            'status'           => 'active',
            'suspended_at'     => null,
            'suspended_reason' => null,
        ]);
        return response()->json(['success' => true]);
    }

    public function plans(): JsonResponse
    {
        return response()->json([
            'plans' => ParentalPlan::where('active', true)->orderBy('price_monthly')->get(['id', 'name', 'slug', 'price_monthly', 'max_devices']),
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $q = ParentalAccount::query()
            ->with('user:id,name,email')
            ->when($request->search, function ($qq, $v) {
                $qq->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$v}%")
                    ->orWhere('email', 'like', "%{$v}%"));
            })
            ->limit(20)
            ->get();
        return response()->json(['accounts' => $q]);
    }
}
