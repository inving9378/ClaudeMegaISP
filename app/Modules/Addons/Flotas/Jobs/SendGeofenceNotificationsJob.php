<?php

namespace App\Modules\Addons\Flotas\Jobs;

use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Services\Notifications\FleetNotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// Sub-fase 3.3 — Envía las notificaciones de geocerca async (queue), sin bloquear la detección.
class SendGeofenceNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** @param int[] $eventIds */
    public function __construct(public array $eventIds)
    {
    }

    public function handle(FleetNotificationDispatcher $dispatcher): void
    {
        $events = FleetGeofenceEvent::with(['vehicle', 'geofence'])
            ->whereIn('id', $this->eventIds)
            ->get();

        foreach ($events as $event) {
            $dispatcher->dispatch($event);
        }
    }
}
