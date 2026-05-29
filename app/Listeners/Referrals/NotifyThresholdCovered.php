<?php

namespace App\Listeners\Referrals;

use App\Events\Referrals\ReferralThresholdCovered;
use App\Services\Referrals\ReferralWhatsAppNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyThresholdCovered implements ShouldQueue
{
    public $queue = 'referrals';

    public function handle(ReferralThresholdCovered $event): void
    {
        try {
            app(ReferralWhatsAppNotifier::class)->notifyThresholdCovered($event->referral);
        } catch (\Throwable $e) {
            Log::error('NotifyThresholdCovered falló', ['error' => $e->getMessage()]);
        }
    }
}
