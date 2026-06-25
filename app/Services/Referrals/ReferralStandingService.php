<?php

namespace App\Services\Referrals;

use App\Models\Referrals\ClientReferralProfile;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralCommission;
use App\Models\Referrals\ReferralReward;

/**
 * Fuente de verdad ÚNICA de los contadores denormalizados del perfil de embajador.
 *
 * Recompute COMPLETO (nunca increment/decrement) → a prueba de drift e idempotente.
 * Alimenta a los tres consumidores: observers (recompute-on-write), el comando
 * RebuildReferralKpisCommand (respaldo nightly) y queda disponible para cualquier
 * lectura que necesite refrescar.
 *
 * Definición canónica (aprobada):
 *  - total_referrals          = referidos no cancelados.
 *  - total_commissions_earned = comisiones aprobadas + aplicadas (lo acreditado).
 *  - total_rewards_earned     = recompensas disponibles + aplicadas.
 *
 * NO toca threshold_amount_paid (lo mantiene AccumulateClientThreshold con freeze),
 * activated_at ni is_eligible.
 */
class ReferralStandingService
{
    /**
     * Recalcula y persiste los 3 contadores del perfil. Devuelve el arreglo persistido.
     */
    public static function computeCountersForProfile(int $profileId): array
    {
        $profile = ClientReferralProfile::find($profileId);
        if (! $profile) {
            return [];
        }

        $clientId = (int) $profile->client_id;

        $counters = [
            'total_referrals' => Referral::where('embajador_id', $clientId)
                ->whereNotIn('status', ['cancelled'])
                ->count(),

            'total_commissions_earned' => (float) ReferralCommission::where('beneficiary_id', $clientId)
                ->whereIn('status', ['approved', 'applied'])
                ->sum('commission_amount'),

            'total_rewards_earned' => ReferralReward::where('embajador_id', $clientId)
                ->whereIn('status', ['available', 'applied'])
                ->count(),
        ];

        // Update por query: persiste SOLO estos 3 + updated_at; no dispara eventos de
        // modelo (evita cualquier recursión con los observers de Referral/Commission).
        ClientReferralProfile::where('id', $profileId)->update($counters);

        return $counters;
    }

    /**
     * Conveniencia para los observers: resuelve el perfil por client_id y recomputa.
     * Si el cliente no tiene perfil de embajador, no hace nada.
     */
    public static function recomputeForClient(?int $clientId): array
    {
        if (! $clientId) {
            return [];
        }

        $profileId = ClientReferralProfile::where('client_id', $clientId)->value('id');

        return $profileId ? self::computeCountersForProfile((int) $profileId) : [];
    }
}
