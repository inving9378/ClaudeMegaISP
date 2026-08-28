<?php

namespace App\Modules\Addons\Flotas\Services\Notifications\Drivers;

use App\Models\User;
use App\Modules\Addons\Flotas\Models\FleetDriverPushToken;
use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Services\Notifications\NotificationChannelInterface;

/**
 * Item #101 (Fase 5.5) — canal push (FCM) para el dispatcher de notificaciones de flota.
 *
 * Scaffold aditivo: ya resuelve el destino (token del conductor) y queda registrado
 * en el mapa de canales, pero el envío real está BLOQUEADO hasta que el item #72
 * (Firebase greenfield) provea credenciales reales — sin project_id configurado,
 * `send()` lanza una excepción clara que el dispatcher captura y loguea como 'failed'
 * con el motivo, nunca finge un envío exitoso.
 */
class PushChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'push';
    }

    public function destination(User $user): ?string
    {
        $token = FleetDriverPushToken::where('user_id', $user->id)
            ->latest('last_seen_at')
            ->value('token');

        return $token ?: null;
    }

    public function send(User $user, FleetGeofenceEvent $event): bool
    {
        if (!config('services.firebase.project_id')) {
            throw new \RuntimeException(
                'Push FCM no disponible: Firebase sin configurar (bloqueado por item #72).'
            );
        }

        // Envío real (HTTP v1 + service account) pendiente de credenciales del item #72.
        throw new \RuntimeException('Envío FCM aún no implementado — pendiente item #72.');
    }
}
