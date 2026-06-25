<?php

namespace App\Observers\Referrals;

use App\Models\Referrals\Referral;
use App\Services\Referrals\ReferralStandingService;

/**
 * Recompute-on-write de los contadores del perfil de embajador ante cualquier cambio
 * en sus referidos. Recompute COMPLETO vía el servicio canónico (idempotente), nunca
 * increment/decrement → a prueba de drift.
 */
class ReferralObserver
{
    public function created(Referral $referral): void
    {
        ReferralStandingService::recomputeForClient((int) $referral->embajador_id);
    }

    public function updated(Referral $referral): void
    {
        ReferralStandingService::recomputeForClient((int) $referral->embajador_id);

        // Si cambió el dueño del referido (raro), refresca también al anterior.
        if ($referral->wasChanged('embajador_id')) {
            ReferralStandingService::recomputeForClient((int) $referral->getOriginal('embajador_id'));
        }
    }

    public function deleted(Referral $referral): void
    {
        ReferralStandingService::recomputeForClient((int) $referral->embajador_id);
    }
}
