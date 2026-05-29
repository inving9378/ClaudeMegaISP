<?php

namespace App\Listeners\Referrals;

use App\Events\Referrals\ReferralCommissionGenerated;
use App\Services\Referrals\ReferralWhatsAppNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyCommissionGenerated implements ShouldQueue
{
    public $queue = 'referrals';

    public function handle(ReferralCommissionGenerated $event): void
    {
        try {
            app(ReferralWhatsAppNotifier::class)->notifyCommissionGenerated($event->commission);
        } catch (\Throwable $e) {
            Log::error('NotifyCommissionGenerated falló', ['error' => $e->getMessage()]);
        }
    }
}
