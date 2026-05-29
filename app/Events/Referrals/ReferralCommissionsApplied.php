<?php

namespace App\Events\Referrals;

use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralCommissionsApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Client $embajador,
        public readonly float $total,
        public readonly int $count,
    ) {}
}
