<?php

namespace Database\Factories;

use App\Models\Referrals\ClientReferralProfile;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientReferralProfileFactory extends Factory
{
    protected $model = ClientReferralProfile::class;

    public function definition(): array
    {
        return [
            'client_id'                 => Client::factory(),
            'referral_code'             => strtoupper($this->faker->unique()->lexify('??????')),
            'referral_link'             => null,
            'plan_type'                 => 'multilevel',
            'activated_at'              => now(),
            'threshold_amount_paid'     => 1500.00,
            'threshold_covered_at'      => now()->subDays(30),
            'is_eligible'               => true,
            'total_commissions_earned'  => 0,
            'total_rewards_earned'      => 0,
            'total_referrals'           => 0,
        ];
    }

    public function multilevel(): static
    {
        return $this->state(['plan_type' => 'multilevel']);
    }

    public function singleReward(): static
    {
        return $this->state(['plan_type' => 'single_reward']);
    }

    public function eligible(): static
    {
        return $this->state([
            'is_eligible'           => true,
            'threshold_amount_paid' => 1500.00,
            'threshold_covered_at'  => now()->subDays(30),
        ]);
    }

    public function notYetEligible(): static
    {
        return $this->state([
            'is_eligible'           => false,
            'threshold_amount_paid' => 500.00,
            'threshold_covered_at'  => null,
            'plan_type'             => null,
            'activated_at'          => null,
        ]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(['client_id' => $client->id]);
    }
}
