<?php

namespace App\Listeners\Referrals;

use App\Events\Referrals\ReferralRewardExpiringSoon;
use App\Services\Referrals\ReferralWhatsAppNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyRewardExpiringSoon implements ShouldQueue
{
    public $queue = 'referrals';

    public function handle(ReferralRewardExpiringSoon $event): void
    {
        try {
            app(ReferralWhatsAppNotifier::class)->notifyRewardExpiringSoon(
                $event->reward,
                $event->daysRemaining,
            );
        } catch (\Throwable $e) {
            Log::error('NotifyRewardExpiringSoon falló', ['error' => $e->getMessage()]);
        }
    }
}
