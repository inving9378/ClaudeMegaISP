<?php

namespace App\Jobs\Referrals;

use App\Events\Referrals\ReferralRewardExpiringSoon;
use App\Models\Referrals\ReferralReward;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WarnExpiringRewards implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('referrals');
    }

    public function handle(): void
    {
        $rewards = ReferralReward::where('status', 'available')
            ->whereBetween('expires_at', [now(), now()->addDays(7)])
            ->whereNull('warning_sent_at')
            ->get();

        foreach ($rewards as $reward) {
            $daysRemaining = (int) now()->diffInDays($reward->expires_at, false);
            $daysRemaining = max(0, $daysRemaining);

            try {
                event(new ReferralRewardExpiringSoon($reward, $daysRemaining));
                $reward->update(['warning_sent_at' => now()]);
            } catch (\Throwable $e) {
                Log::error('WarnExpiringRewards: error al procesar reward', [
                    'reward_id' => $reward->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }
}
