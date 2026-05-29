<?php

namespace App\Jobs\Referrals;

use App\Jobs\Referrals\Concerns\MeasuresPerformance;
use App\Models\Referrals\ReferralReward;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireReferralRewards implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MeasuresPerformance;

    public function __construct()
    {
        $this->onQueue('referrals');
    }

    public function handle(): void
    {
        $this->startMeasure();
        $count = ReferralReward::where('status', 'available')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
        $this->countProcessed($count);
        $this->recordSuccess(['expired' => $count]);
    }
}
