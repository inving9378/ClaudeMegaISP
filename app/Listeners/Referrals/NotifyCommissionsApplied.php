<?php

namespace App\Listeners\Referrals;

use App\Events\Referrals\ReferralCommissionsApplied;
use App\Services\Referrals\ReferralWhatsAppNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyCommissionsApplied implements ShouldQueue
{
    public $queue = 'referrals';

    public function handle(ReferralCommissionsApplied $event): void
    {
        try {
            app(ReferralWhatsAppNotifier::class)->notifyCommissionsApplied(
                $event->embajador,
                $event->total,
                $event->count,
            );
        } catch (\Throwable $e) {
            Log::error('NotifyCommissionsApplied falló', ['error' => $e->getMessage()]);
        }
    }
}
