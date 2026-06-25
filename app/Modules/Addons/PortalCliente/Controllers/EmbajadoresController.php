<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientMainInformation;
use App\Models\Referrals\ClientReferralProfile;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralCommission;
use App\Models\Referrals\ReferralProspect;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralSetting;
use App\Services\Tenant\CurrentClientResolver;
use Illuminate\Support\Facades\Auth;

/**
 * Panel "Embajadores Meganet" dentro del portal de cliente (guard `cliente`).
 *
 * PRIMERA rebanada que activa la lectura ->forClient() de la fundación de tenancy:
 * el client_id viene de CurrentClientResolver::resolve() (rama guard cliente) y TODA
 * consulta a los modelos de Referrals se scopea con ->forClient($clientId). Solo
 * lectura. Reusa el mismo cálculo de standing del API móvil (no se duplica lógica de
 * negocio: mismos campos de ClientReferralProfile + ReferralSetting).
 */
class EmbajadoresController extends Controller
{
    public function index(CurrentClientResolver $resolver)
    {
        $clientId = $resolver->resolve();
        $cmi      = Auth::guard('cliente')->user();
        $setting  = ReferralSetting::current();

        // Fail-closed: sin client_id resuelto no se expone nada. forClient(null) ya
        // devuelve cero, pero evitamos incluso la query.
        $profile = $clientId ? ClientReferralProfile::forClient($clientId)->first() : null;

        // Este cliente NO es embajador todavía → estado vacío con CTA, NO error.
        if (! $profile) {
            return view('addon-portal-cliente::embajadores', [
                'cmi'     => $cmi,
                'active'  => false,
                'setting' => $setting,
            ]);
        }

        // Comisiones donde el cliente es BENEFICIARIO (forClient = beneficiary_id).
        $commByStatus = ReferralCommission::forClient($clientId)
            ->selectRaw('status, SUM(commission_amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $commissions = ReferralCommission::forClient($clientId)
            ->orderByDesc('created_at')->limit(20)
            ->get(['id', 'commission_amount', 'status', 'period_month', 'period_year', 'level', 'created_at']);

        // Referidos / prospectos / recompensas (forClient = embajador_id).
        $referrals = Referral::forClient($clientId)
            ->orderByDesc('created_at')->limit(30)
            ->get(['id', 'referred_client_id', 'chain_depth', 'status', 'commissions_paid_count', 'created_at']);

        $prospects = ReferralProspect::forClient($clientId)
            ->orderByDesc('created_at')->limit(30)
            ->get(['id', 'name', 'phone', 'status', 'converted_client_id', 'created_at']);

        $rewards = ReferralReward::forClient($clientId)
            ->orderByDesc('created_at')->limit(20)
            ->get(['id', 'type', 'status', 'available_at', 'applied_at', 'expires_at', 'created_at']);

        // Nombres de los clientes referidos en un solo lote (evita N+1).
        $names = ClientMainInformation::whereIn(
            'client_id',
            $referrals->pluck('referred_client_id')->filter()->unique()->values()
        )->pluck('name', 'client_id');

        // Standing: progreso al umbral de activación (mismo cálculo que el API).
        $thresholdPaid  = (float) $profile->threshold_amount_paid;
        $thresholdTotal = (float) $setting->threshold_amount;
        $progress       = $thresholdTotal > 0 ? min(100, round($thresholdPaid / $thresholdTotal * 100)) : 0;

        return view('addon-portal-cliente::embajadores', [
            'cmi'            => $cmi,
            'active'         => true,
            'profile'        => $profile,
            'setting'        => $setting,
            'planLabel'      => $profile->plan_type === 'multilevel' ? 'Multinivel' : 'Mensualidad gratis',
            'commByStatus'   => $commByStatus,
            'commissions'    => $commissions,
            'referrals'      => $referrals,
            'prospects'      => $prospects,
            'rewards'        => $rewards,
            'names'          => $names,
            'thresholdPaid'  => $thresholdPaid,
            'thresholdTotal' => $thresholdTotal,
            'progress'       => $progress,
            'windowMonths'   => (int) $setting->duration_months,
        ]);
    }
}
