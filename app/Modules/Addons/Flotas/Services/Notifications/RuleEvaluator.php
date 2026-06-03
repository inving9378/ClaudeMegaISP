<?php

namespace App\Modules\Addons\Flotas\Services\Notifications;

use App\Models\User;
use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Models\FleetGeofenceRule;
use Carbon\Carbon;

/**
 * Sub-fase 3.4 — Capa OPCIONAL de reglas (horarios/días/vehículo/geocerca) sobre
 * las preferencias de 3.3.
 *
 * Comportamiento mixto (Opción 3):
 *  - Usuario SIN reglas activas → ALLOW (hereda 3.3 intacto, sin filtrar canales).
 *  - Usuario CON reglas → al menos una debe coincidir; si ninguna coincide → DENY.
 *
 * Timezone: se asume la del servidor (sin tz por usuario — fuera de alcance 3.4).
 */
class RuleEvaluator
{
    /**
     * @return array{allowed: bool, matched_rule_id: ?int, channels: ?array}
     */
    public function evaluate(User $user, FleetGeofenceEvent $event): array
    {
        $rules = FleetGeofenceRule::where('user_id', $user->id)
            ->where('active', true)
            ->get();

        // Sin reglas → comportamiento 3.3 (permitir, sin filtrar canales).
        if ($rules->isEmpty()) {
            return ['allowed' => true, 'matched_rule_id' => null, 'channels' => null];
        }

        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $event)) {
                return [
                    'allowed'         => true,
                    'matched_rule_id' => $rule->id,
                    'channels'        => $rule->channels ?: null,
                ];
            }
        }

        // Hay reglas pero ninguna coincide → silencio explícito.
        return ['allowed' => false, 'matched_rule_id' => null, 'channels' => null];
    }

    private function ruleMatches(FleetGeofenceRule $rule, FleetGeofenceEvent $event): bool
    {
        // Vehículos ([] = todos)
        if (!empty($rule->vehicle_ids) && !in_array($event->vehicle_id, $rule->vehicle_ids)) {
            return false;
        }
        // Geocercas ([] = todas)
        if (!empty($rule->geofence_ids) && !in_array($event->geofence_id, $rule->geofence_ids)) {
            return false;
        }
        // Tipo de evento (siempre definido, ≥1)
        if (!in_array($event->event_type, $rule->event_types ?? [], true)) {
            return false;
        }
        // Día de la semana ISO 1-7 ([] = todos)
        if (!empty($rule->days_of_week)) {
            $eventDay = Carbon::parse($event->occurred_at)->dayOfWeekIso;
            if (!in_array($eventDay, $rule->days_of_week)) {
                return false;
            }
        }
        // Ventana horaria (maneja cruce de medianoche)
        if ($rule->time_from && $rule->time_to) {
            $eventTime = Carbon::parse($event->occurred_at)->format('H:i:s');
            $from = $this->normalizeTime($rule->time_from);
            $to   = $this->normalizeTime($rule->time_to);

            if ($from <= $to) {
                // Ventana normal (ej 09:00:00–17:00:00)
                if ($eventTime < $from || $eventTime > $to) {
                    return false;
                }
            } else {
                // Ventana que cruza medianoche (ej 22:00:00–06:00:00):
                // permitida si está DESPUÉS de from O ANTES de to.
                if ($eventTime < $from && $eventTime > $to) {
                    return false;
                }
            }
        }

        return true;
    }

    /** Asegura formato H:i:s para comparar (acepta "22:00" o "22:00:00"). */
    private function normalizeTime(string $t): string
    {
        return substr_count($t, ':') === 1 ? $t . ':00' : $t;
    }
}
