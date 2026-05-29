<?php

namespace Database\Factories;

use App\Models\Referrals\Referral;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition(): array
    {
        $embajador = Client::factory()->create();

        return [
            'embajador_id'                   => $embajador->id,
            'referred_client_id'             => Client::factory(),
            'chain_path'                     => '/' . $embajador->id . '/',
            'chain_depth'                    => 1,
            'referred_threshold_amount_paid' => 0,
            'referred_threshold_covered_at'  => null,
            'commission_window_start'        => null,
            'commissions_paid_count'         => 0,
            'status'                         => 'pending_threshold',
        ];
    }

    public function pendingThreshold(): static
    {
        return $this->state([
            'referred_threshold_amount_paid' => 0,
            'status'                         => 'pending_threshold',
        ]);
    }

    public function active(): static
    {
        return $this->state([
            'referred_threshold_amount_paid' => 1500.00,
            'referred_threshold_covered_at'  => now()->subDays(10),
            'commission_window_start'        => now()->subDays(10),
            'commissions_paid_count'         => 0,
            'status'                         => 'active',
        ]);
    }

    public function withCommissionsCount(int $count): static
    {
        return $this->state(['commissions_paid_count' => $count]);
    }

    public function forEmbajador(Client $embajador): static
    {
        return $this->state([
            'embajador_id' => $embajador->id,
            'chain_path'   => '/' . $embajador->id . '/',
        ]);
    }

    public function forReferredClient(Client $referred): static
    {
        return $this->state(['referred_client_id' => $referred->id]);
    }
}
