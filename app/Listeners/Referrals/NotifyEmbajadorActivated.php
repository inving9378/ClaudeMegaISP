<?php

namespace App\Listeners\Referrals;

use App\Events\Referrals\EmbajadorActivated;
use App\Services\Referrals\ReferralWhatsAppNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyEmbajadorActivated implements ShouldQueue
{
    public string $queue = 'referrals';

    public function handle(EmbajadorActivated $event): void
    {
        try {
            app(ReferralWhatsAppNotifier::class)->notifyEmbajadorActivated($event->profile);
        } catch (\Throwable $e) {
            Log::error('NotifyEmbajadorActivated falló', [
                'client_id' => $event->profile->client_id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
