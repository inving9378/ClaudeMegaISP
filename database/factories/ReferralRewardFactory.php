<?php

namespace Database\Factories;

use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\Referral;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralRewardFactory extends Factory
{
    protected $model = ReferralReward::class;

    public function definition(): array
    {
        return [
            'embajador_id'        => Client::factory(),
            'referral_id'         => Referral::factory(),
            'type'                => 'free_month',
            'plan_value_snapshot' => 299.00,
            'status'              => 'available',
            'available_at'        => now()->subMonth(),
            'expires_at'          => now()->addMonths(11),
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
