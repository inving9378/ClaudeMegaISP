<?php

namespace App\Modules\Addons\Flotas\Services\Notifications\Drivers;

use App\Models\User;
use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Services\Notifications\GeofenceEventPresenter;
use App\Modules\Addons\Flotas\Services\Notifications\NotificationChannelInterface;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;

class WhatsappChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'whatsapp';
    }

    public function destination(User $user): ?string
    {
        $phone = preg_replace('/[^0-9]/', '', (string) ($user->phone ?? ''));
        return $phone !== '' ? $phone : null;
    }

    public function send(User $user, FleetGeofenceEvent $event): bool
    {
        $phone = $this->destination($user);
        $d = GeofenceEventPresenter::data($event);

        $text = "🚗 {$d['vehicle_name']} ({$d['plates']}) "
            . mb_strtolower($d['action']) . " {$d['geofence_name']} a las {$d['time_human']}.\n"
            . "Ver detalle: {$d['url']}";

        // Reusa el servicio Evolution existente (company 1). Lanza excepción si la API falla.
        (new EvolutionApiService())->sendText($phone, $text);
        return true;
    }
}
