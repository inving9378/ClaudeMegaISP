<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalAlert;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Models\ParentalPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::dashboard.index', [
            'kpis' => $this->kpis(),
        ]);
    }

    public function summary(): JsonResponse
    {
        $monthStart = now()->startOfMonth();

        return response()->json([
            'kpis' => $this->kpis(),
            'clients_by_plan' => ParentalAccount::query()
                ->join('parental_plans', 'parental_plans.id', '=', 'parental_accounts.plan_id')
                ->where('parental_accounts.status', 'active')
                ->select('parental_plans.name as plan', DB::raw('count(*) as total'))
                ->groupBy('parental_plans.name')
                ->get(),
            'recent_alerts' => ParentalAlert::with(['profile:id,name', 'account:id,client_id'])
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'revenue_this_month' => ParentalAccount::query()
                ->join('parental_plans', 'parental_plans.id', '=', 'parental_accounts.plan_id')
                ->where('parental_accounts.status', 'active')
                ->where('parental_accounts.licensed_at', '<=', now())
                ->sum('parental_plans.price_monthly'),
        ]);
    }

    private function kpis(): array
    {
        return [
            'total_clients' => ParentalAccount::count(),
            'active_clients' => ParentalAccount::where('status', 'active')->count(),
            'total_devices' => ParentalDevice::count(),
            'online_devices' => ParentalDevice::where('status', 'online')->count(),
            'unread_alerts' => ParentalAlert::whereNull('read_at')->count(),
            'total_plans' => ParentalPlan::where('active', true)->count(),
        ];
    }
}
