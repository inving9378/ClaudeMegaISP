<?php

namespace Database\Factories;

use App\Models\Referrals\ReferralCommissionTier;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralCommissionTierFactory extends Factory
{
    protected $model = ReferralCommissionTier::class;

    public function definition(): array
    {
        return [
            'tier_name'     => 'Standard',
            'speed_min_mbps' => 1,
            'speed_max_mbps' => 300,
            'level_1_pct'   => 5.00,
            'level_2_pct'   => 3.00,
            'level_3_pct'   => 2.00,
            'level_4_pct'   => 1.00,
            'level_5_pct'   => 0.50,
            'active'        => 1,
        ];
    }
}
