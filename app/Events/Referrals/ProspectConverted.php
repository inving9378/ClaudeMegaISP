<?php

namespace App\Events\Referrals;

use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralProspect;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProspectConverted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Referral $referral,
        public readonly ?ReferralProspect $prospect,
    ) {}
}
