<?php

namespace Tests\Feature\Referrals;

use App\Jobs\Referrals\ApplyReferralCommissions;
use App\Models\Balance;
use App\Models\Referrals\ReferralCommission;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\CreatesApplication;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;

class ApplyReferralCommissionsTest extends LaravelTestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function makeClientWithBalance(float $initialBalance = 0.00): Client
    {
        $client = Client::factory()->create();
        Balance::create([
            'balanceable_id'   => $client->id,
            'balanceable_type' => 'App\Models\Client',
            'amount'           => $initialBalance,
        ]);
        return $client;
    }

    // 10. Acreditar comisiones vencidas incrementa el balance y marca applied
    public function test_apply_commissions_acredita_balance_y_marca_applied(): void
    {
        $beneficiary = $this->makeClientWithBalance(100.00);

        // Dos comisiones cuyo apply_after_at ya venció
        $c1 = ReferralCommission::factory()->forBeneficiary($beneficiary)->create([
            'commission_amount' => 14.95,
            'apply_after_at'    => now()->subDays(1),
        ]);
        $c2 = ReferralCommission::factory()->forBeneficiary($beneficiary)->create([
            'commission_amount' => 8.97,
            'apply_after_at'    => now()->subDays(2),
        ]);

        (new ApplyReferralCommissions)->handle();

        $balance = Balance::where('balanceable_id', $beneficiary->id)
            ->where('balanceable_type', 'App\Models\Client')
            ->first();

        $this->assertEquals(123.92, round((float) $balance->amount, 2));

        foreach ([$c1, $c2] as $commission) {
            $fresh = $commission->fresh();
            $this->assertEquals('applied', $fresh->status);
            $this->assertNotNull($fresh->applied_at);
        }
    }

    // 11. Comisiones cuyo apply_after_at aún no venció no se acreditan
    public function test_apply_commissions_ignora_comisiones_no_vencidas(): void
    {
        $beneficiary = $this->makeClientWithBalance(50.00);

        ReferralCommission::factory()->forBeneficiary($beneficiary)->pendingWindow()->create([
            'commission_amount' => 14.95,
        ]);

        (new ApplyReferralCommissions)->handle();

        $balance = Balance::where('balanceable_id', $beneficiary->id)
            ->where('balanceable_type', 'App\Models\Client')
            ->first();

        $this->assertEquals(50.00, round((float) $balance->amount, 2));

        $this->assertDatabaseMissing('referral_commissions', [
            'beneficiary_id' => $beneficiary->id,
            'status'         => 'applied',
        ]);
    }
}
