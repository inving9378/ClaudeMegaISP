<?php

namespace Database\Factories;

use App\Models\Referrals\ReferralCommission;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Models\ClientInvoice;
use App\Models\Referrals\Referral;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralCommissionFactory extends Factory
{
    protected $model = ReferralCommission::class;

    public function definition(): array
    {
        return [
            'beneficiary_id'    => Client::factory(),
            'referral_id'       => Referral::factory(),
            'invoice_id'        => ClientInvoice::factory(),
            'level'             => 1,
            'commission_pct'    => 5.00,
            'base_amount'       => 299.00,
            'commission_amount' => 14.95,
            'period_month'      => now()->month,
            'period_year'       => now()->year,
            'status'            => 'approved',
            'apply_after_at'    => now()->subDay(),
            'applied_at'        => null,
        ];
    }

    public function pendingWindow(): static
    {
        return $this->state(['apply_after_at' => now()->addDays(15)]);
    }

    public function forBeneficiary(Client $client): static
    {
        return $this->state(['beneficiary_id' => $client->id]);
    }
}
