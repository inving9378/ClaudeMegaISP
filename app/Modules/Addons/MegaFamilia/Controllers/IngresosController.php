<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class IngresosController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::ingresos.index');
    }

    public function data(): JsonResponse
    {
        $byPlan = ParentalAccount::query()
            ->join('parental_plans', 'parental_plans.id', '=', 'parental_accounts.plan_id')
            ->where('parental_accounts.status', 'active')
            ->select(
                'parental_plans.name',
                DB::raw('COUNT(*) as accounts'),
                DB::raw('SUM(parental_plans.price_monthly) as monthly_revenue')
            )
            ->groupBy('parental_plans.id', 'parental_plans.name')
            ->get();

        $byMonth = ParentalAccount::query()
            ->join('parental_plans', 'parental_plans.id', '=', 'parental_accounts.plan_id')
            ->where('parental_accounts.status', 'active')
            ->whereNotNull('parental_accounts.licensed_at')
            ->where('parental_accounts.licensed_at', '>=', now()->subMonths(11)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(parental_accounts.licensed_at,'%Y-%m') as month"),
                DB::raw('SUM(parental_plans.price_monthly) as revenue')
            )
            ->groupBy('month')->orderBy('month')->get();

        return response()->json([
            'by_plan' => $byPlan,
            'by_month' => $byMonth,
            'mrr' => (float) $byPlan->sum('monthly_revenue'),
        ]);
    }

    public function export()
    {
        // TODO: barryvdh/dompdf — exportar tabla. Placeholder.
        return response()->json([
            'success' => false,
            'error' => 'Export PDF aún no implementado',
        ], 501);
    }
}
