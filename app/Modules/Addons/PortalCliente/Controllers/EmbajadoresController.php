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

        // FUENTE DE VERDAD ÚNICA: los titulares (KPI) y los encabezados de las tablas
        // salen del MISMO cálculo vivo, para que no puedan volver a divergir.
        $standing = $this->buildStanding($clientId);

        // Listados de detalle (mismas consultas scopeadas; los totales del encabezado
        // los provee $standing, no ->count() de la colección recortada).
        $commissions = ReferralCommission::forClient($clientId)
            ->orderByDesc('created_at')->limit(20)
            ->get(['id', 'commission_amount', 'status', 'period_month', 'period_year', 'level', 'created_at']);

        $referrals = Referral::forClient($clientId)
            ->orderByDesc('created_at')->limit(30)
            ->get(['id', 'referred_client_id', 'chain_depth', 'status', 'referred_threshold_covered_at', 'commissions_paid_count', 'created_at']);

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

        return view('addon-portal-cliente::embajadores', [
            'cmi'           => $cmi,
            'active'        => true,
            'profile'       => $profile,
            'setting'       => $setting,
            'planLabel'     => $profile->plan_type === 'multilevel' ? 'Multinivel' : 'Mensualidad gratis',
            'standing'      => $standing,
            'commissions'   => $commissions,
            'referrals'     => $referrals,
            'prospects'     => $prospects,
            'rewards'       => $rewards,
            'names'         => $names,
            'thresholdEach' => (float) $setting->threshold_amount,
            'windowMonths'  => (int) $setting->duration_months,
        ]);
    }

    /**
     * Fuente de verdad ÚNICA del standing del embajador, todo en vivo y scopeado por
     * cliente. Alimenta tanto las tarjetas KPI como los encabezados de las tablas para
     * que el titular SIEMPRE concuerde con el detalle.
     *
     * NOTA de semántica: "activación" NO es lo mismo que "comisiones acumuladas". El
     * umbral de ${{threshold}} es POR REFERIDO (consumo del cliente referido, campo
     * Referral.referred_threshold_amount_paid); aquí se reporta cuántos referidos ya lo
     * cubrieron (referred_threshold_covered_at). Las comisiones son lo que el embajador
     * gana (ReferralCommission). Por eso son métricas distintas.
     */
    private function buildStanding(int $clientId): array
    {
        $referralsTotal = Referral::forClient($clientId)->count();
        $activated      = Referral::forClient($clientId)->whereNotNull('referred_threshold_covered_at')->count();

        $byStatus = ReferralCommission::forClient($clientId)
            ->selectRaw('status, SUM(commission_amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Comisiones ganadas = aprobadas + aplicadas (lo realmente acreditado).
        $earned = (float) (($byStatus['approved'] ?? 0) + ($byStatus['applied'] ?? 0));

        return [
            'referrals_total'      => $referralsTotal,
            'activated_referrals'  => $activated,
            'activation_pct'       => $referralsTotal > 0 ? (int) round($activated / $referralsTotal * 100) : 0,
            'commissions_earned'   => $earned,
            'commissions_by_status' => $byStatus,
            'rewards_total'        => ReferralReward::forClient($clientId)->count(),
        ];
    }
}
