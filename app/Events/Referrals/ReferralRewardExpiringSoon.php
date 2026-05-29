<?php

namespace App\Events\Referrals;

use App\Models\Referrals\ReferralReward;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralRewardExpiringSoon
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ReferralReward $reward,
        public readonly int $daysRemaining,
    ) {}
}
