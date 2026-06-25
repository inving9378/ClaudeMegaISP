<?php

namespace App\Observers\Referrals;

use App\Models\Referrals\ReferralCommission;
use App\Services\Referrals\ReferralStandingService;

/**
 * Recompute-on-write de los contadores del perfil ante cualquier cambio en las
 * comisiones donde el cliente es beneficiario. Recompute COMPLETO vía el servicio
 * canónico (idempotente). Cubre los cambios de status (p.ej. approved → cancelled),
 * por eso updated también recomputa.
 */
class ReferralCommissionObserver
{
    public function created(ReferralCommission $commission): void
    {
        ReferralStandingService::recomputeForClient((int) $commission->beneficiary_id);
    }

    public function updated(ReferralCommission $commission): void
    {
        ReferralStandingService::recomputeForClient((int) $commission->beneficiary_id);

        if ($commission->wasChanged('beneficiary_id')) {
            ReferralStandingService::recomputeForClient((int) $commission->getOriginal('beneficiary_id'));
        }
    }

    public function deleted(ReferralCommission $commission): void
    {
        ReferralStandingService::recomputeForClient((int) $commission->beneficiary_id);
    }
}
