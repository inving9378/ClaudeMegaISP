<?php

namespace App\Events\Referrals;

use App\Models\Referrals\ClientReferralProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmbajadorActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(public ClientReferralProfile $profile) {}
}
