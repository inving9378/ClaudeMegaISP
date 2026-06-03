<?php

namespace App\Modules\Addons\Flotas\Services\Notifications;

use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;

/** Normaliza un evento de geocerca a datos listos para presentar (email/whatsapp/…). */
class GeofenceEventPresenter
{
    public static function data(FleetGeofenceEvent $event): array
    {
        $vehicle  = $event->vehicle;
        $geofence = $event->geofence;
        $isEnter  = $event->event_type === 'enter';

        return [
            'vehicle_name'  => $vehicle?->display_name ?? ('Vehículo #' . $event->vehicle_id),
            'plates'        => $vehicle?->plates ?: 'sin placas',
            'action'        => $isEnter ? 'Entró a' : 'Salió de',
            'action_emoji'  => $isEnter ? '🟢' : '🟠',
            'geofence_name' => $geofence?->name ?? ('Geocerca #' . $event->geofence_id),
            'time_human'    => optional($event->occurred_at)->format('d/m/Y H:i') ?? '—',
            'url'           => url('/flotas/' . $event->vehicle_id . '?tab=gps'),
        ];
    }
}
