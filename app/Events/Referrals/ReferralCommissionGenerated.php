<?php

namespace App\Events\Referrals;

use App\Models\Referrals\ReferralCommission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralCommissionGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ReferralCommission $commission) {}
}
