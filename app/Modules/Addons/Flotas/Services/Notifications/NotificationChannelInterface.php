<?php

namespace App\Modules\Addons\Flotas\Services\Notifications;

use App\Models\User;
use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;

/**
 * Sub-fase 3.3 — Canal de notificación de eventos de geocerca.
 *
 * Abstracción para que sumar canales futuros (push/FCM item #72, sms) sea trivial:
 * basta implementar esta interfaz y registrarlo en FleetNotificationDispatcher::CHANNELS.
 */
interface NotificationChannelInterface
{
    /** Identificador del canal: 'email', 'whatsapp', 'push', 'sms'. */
    public function name(): string;

    /** Destino del usuario para este canal (email, teléfono…). null = no enviable → skipped. */
    public function destination(User $user): ?string;

    /**
     * Envía la notificación. Devuelve true si fue aceptada por el transporte.
     * Puede lanzar excepción ante un fallo de transporte (el dispatcher lo captura y loguea).
     */
    public function send(User $user, FleetGeofenceEvent $event): bool;
}
