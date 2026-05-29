<?php

namespace App\Listeners\Referrals;

use App\Events\Referrals\ProspectConverted;
use App\Services\Referrals\ReferralWhatsAppNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyProspectConverted implements ShouldQueue
{
    public $queue = 'referrals';

    public function handle(ProspectConverted $event): void
    {
        try {
            app(ReferralWhatsAppNotifier::class)->notifyProspectConverted(
                $event->referral,
                $event->prospect,
            );
        } catch (\Throwable $e) {
            Log::error('NotifyProspectConverted falló', ['error' => $e->getMessage()]);
        }
    }
}
