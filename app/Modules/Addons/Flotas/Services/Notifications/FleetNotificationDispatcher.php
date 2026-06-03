<?php

namespace App\Modules\Addons\Flotas\Services\Notifications;

use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Models\FleetNotificationLog;
use App\Modules\Addons\Flotas\Models\FleetNotificationPreference;
use App\Modules\Addons\Flotas\Services\Notifications\Drivers\EmailChannel;
use App\Modules\Addons\Flotas\Services\Notifications\Drivers\WhatsappChannel;
use Illuminate\Support\Facades\Log;

/**
 * Sub-fase 3.3 — Despacha notificaciones de un evento de geocerca a los usuarios
 * suscritos, por cada canal habilitado, registrando cada intento en el log.
 *
 * Sumar un canal nuevo (push/FCM item #72, sms): implementar NotificationChannelInterface
 * y añadirlo al mapa CHANNELS. Nada más cambia.
 */
class FleetNotificationDispatcher
{
    private const CHANNELS = [
        'email'    => EmailChannel::class,
        'whatsapp' => WhatsappChannel::class,
        // 'push'  => PushChannel::class,  // item #72 (greenfield Firebase)
        // 'sms'   => SmsChannel::class,
    ];

    /** @return array resultados [{channel,user_id,status}] */
    public function dispatch(FleetGeofenceEvent $event): array
    {
        $event->loadMissing(['vehicle', 'geofence']);
        $vehicle = $event->vehicle;
        if (!$vehicle) {
            return [];
        }
        $vehicleClient = $vehicle->client_id;

        $prefs = FleetNotificationPreference::active()
            ->where(fn ($q) => $q->whereNull('vehicle_id')->orWhere('vehicle_id', $event->vehicle_id))
            ->where(fn ($q) => $q->whereNull('geofence_id')->orWhere('geofence_id', $event->geofence_id))
            ->with('user')
            ->get()
            ->filter(fn ($p) => $p->user !== null)
            ->filter(fn ($p) => in_array($event->event_type, $p->event_types ?? [], true))
            // Multi-tenancy: el usuario debe pertenecer al mismo tenant que el vehículo.
            ->filter(fn ($p) => (string) ($p->user->client_id ?? '') === (string) ($vehicleClient ?? ''));

        $results = [];
        $seen = [];   // dedup por user_id|channel dentro de este evento

        $evaluator = app(RuleEvaluator::class);

        foreach ($prefs as $pref) {
            $user = $pref->user;

            // Sub-fase 3.4: capa OPCIONAL de reglas. Sin reglas → allowed=true, channels=null (3.3 intacto).
            $eval = $evaluator->evaluate($user, $event);
            if (!$eval['allowed']) {
                // Registra el filtrado por cada canal que la preferencia hubiera usado (auditoría).
                foreach (($pref->channels ?? []) as $ch) {
                    $key = $user->id . '|' . $ch;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $results[] = $this->log($event, $user->id, $ch, null, 'skipped', 'Filtrado por reglas del usuario (3.4)');
                }
                continue;
            }

            // Si una regla define canales, se intersectan con los de la preferencia.
            $channels = $eval['channels']
                ? array_values(array_intersect($eval['channels'], $pref->channels ?? []))
                : ($pref->channels ?? []);

            foreach ($channels as $channelName) {
                $key = $user->id . '|' . $channelName;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $driver = $this->resolveChannel($channelName);
                if (!$driver) {
                    // Canal declarado en preferencias pero aún no implementado (ej. push).
                    $results[] = $this->log($event, $user->id, $channelName, null, 'skipped', 'Canal no disponible aún');
                    continue;
                }
                $results[] = $this->sendVia($driver, $user, $event);
            }
        }

        return $results;
    }

    private function sendVia(NotificationChannelInterface $driver, $user, FleetGeofenceEvent $event): array
    {
        $dest = $driver->destination($user);
        if (!$dest) {
            return $this->log($event, $user->id, $driver->name(), null, 'skipped',
                'Usuario sin destino para ' . $driver->name());
        }

        try {
            $driver->send($user, $event);
            return $this->log($event, $user->id, $driver->name(), $dest, 'sent', null);
        } catch (\Throwable $e) {
            Log::warning('Flotas: notificación falló', [
                'event_id' => $event->id, 'user_id' => $user->id,
                'channel' => $driver->name(), 'error' => $e->getMessage(),
            ]);
            return $this->log($event, $user->id, $driver->name(), $dest, 'failed',
                mb_substr($e->getMessage(), 0, 1000));
        }
    }

    private function log(FleetGeofenceEvent $event, int $userId, string $channel, ?string $dest, string $status, ?string $error): array
    {
        FleetNotificationLog::create([
            'event_id'      => $event->id,
            'user_id'       => $userId,
            'channel'       => $channel,
            'destination'   => $dest,
            'status'        => $status,
            'error_message' => $error,
            'sent_at'       => $status === 'sent' ? now() : null,
            'created_at'    => now(),
        ]);

        return ['channel' => $channel, 'user_id' => $userId, 'status' => $status];
    }

    private function resolveChannel(string $name): ?NotificationChannelInterface
    {
        $cls = self::CHANNELS[$name] ?? null;
        return $cls ? app($cls) : null;
    }
}
