<?php

namespace Tests\Feature\Referrals;

use App\Jobs\Referrals\ExpireReferralRewards;
use App\Models\Referrals\ReferralReward;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\CreatesApplication;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;

class ExpireReferralRewardsTest extends LaravelTestCase
{
    use CreatesApplication, DatabaseTransactions;

    // 12. Sólo expira rewards con expires_at pasado; las vigentes quedan intactas
    public function test_expire_rewards_marca_expiradas_pero_no_disponibles_intactas(): void
    {
        $expired  = ReferralReward::factory()->expired()->create();
        $vigente  = ReferralReward::factory()->create(); // expires_at = now()+11 meses

        (new ExpireReferralRewards)->handle();

        $this->assertEquals('expired', $expired->fresh()->status);
        $this->assertEquals('available', $vigente->fresh()->status);
    }
}
