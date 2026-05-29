<?php

namespace App\Events\Referrals;

use App\Models\Referrals\Referral;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralThresholdCovered
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Referral $referral) {}
}
