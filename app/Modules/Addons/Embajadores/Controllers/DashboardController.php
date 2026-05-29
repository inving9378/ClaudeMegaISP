<?php

namespace App\Modules\Addons\Embajadores\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Referrals\ClientReferralProfile;
use App\Models\Referrals\ReferralCommission;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralSetting;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index()
    {
        return view('addon-embajadores::dashboard.index', [
            'program_active' => ReferralSetting::current()->program_active,
        ]);
    }

    public function summary(): JsonResponse
    {
        $monthStart = now()->startOfMonth();

        $totalEmbajadores = ClientReferralProfile::whereNotNull('plan_type')->count();
        $embajadoresActivos = ClientReferralProfile::where('is_eligible', true)->whereNotNull('plan_type')->count();

        $comisionesEsteMes = ReferralCommission::where('period_year', now()->year)
            ->where('period_month', now()->month)
            ->where('status', '!=', 'cancelled')
            ->sum('commission_amount');

        $comisionesPendientes = ReferralCommission::where('status', 'pending')->count();

        $recompensasDisponibles = ReferralReward::where('status', 'available')->count();

        $porPlan = ClientReferralProfile::whereNotNull('plan_type')
            ->selectRaw('plan_type, count(*) as total')
            ->groupBy('plan_type')
            ->pluck('total', 'plan_type');

        $recentComisiones = ReferralCommission::with([
                'beneficiary:id',
                'beneficiary.client_main_information:client_id,name,father_last_name',
            ])
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return response()->json([
            'kpis' => [
                'total_embajadores'        => $totalEmbajadores,
                'embajadores_activos'      => $embajadoresActivos,
                'comisiones_este_mes'      => round($comisionesEsteMes, 2),
                'comisiones_pendientes'    => $comisionesPendientes,
                'recompensas_disponibles'  => $recompensasDisponibles,
            ],
            'por_plan'          => $porPlan,
            'recent_comisiones' => $recentComisiones,
        ]);
    }
}
