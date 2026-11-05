<?php

namespace App\Modules\Addons\Flotas\Services\Notifications;

use App\Models\ClientMainInformation;
use App\Modules\Addons\Flotas\Mail\FleetClientRuleAlertMail;
use App\Modules\Addons\Flotas\Models\FleetClientAlertContact;
use App\Modules\Addons\Flotas\Models\FleetClientAlertDelivery;
use App\Modules\Addons\Flotas\Models\FleetClientWhatsappUsage;
use App\Modules\Addons\Flotas\Models\FleetDevice;
use App\Modules\Addons\Flotas\Models\FleetDocument;
use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Models\FleetGeofenceRule;
use App\Modules\Addons\Flotas\Models\FleetPosition;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\ServicePredictionService;
use App\Modules\Addons\Hub\Services\Correo;
use App\Modules\Addons\Hub\Services\Servicios;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Motor de reglas de alerta del CLIENTE (portal Flotas, origen='cliente', user_id NULL).
 *
 * Aparte del FleetNotificationDispatcher (user-based, panel Meganet): aquí el destinatario
 * es el contacto del cliente dueño del vehículo (client_main_information) y el rastro vive
 * en fleet_client_alerts, que el panel no lee. Anti-spam: una alerta por regla+vehículo+asunto
 * dentro de la ventana de enfriamiento de cada tipo.
 */
class ClientRuleEngine
{
    /** Minutos de enfriamiento por tipo. */
    private const COOLDOWN = [
        'geocerca'      => 0,
        'velocidad'     => 15,
        'horario'       => 60,
        'sin_conexion'  => 1440,
        'documento'     => 1440,
        'mantenimiento' => 1440,
    ];

    /** Velocidad y horario, sobre posiciones recién guardadas de un vehículo. */
    public function onPositions(int $vehicleId, Collection $positions): void
    {
        $vehicle = FleetVehicle::find($vehicleId);
        if (!$vehicle || !$vehicle->client_id || $positions->isEmpty()) {
            return;
        }

        foreach ($this->rulesFor($vehicle, ['velocidad', 'horario']) as $rule) {
            foreach ($positions as $p) {
                if ($rule->tipo === 'velocidad') {
                    $max = (int) ($rule->params['max_kmh'] ?? 0);
                    if ($max > 0 && (float) $p->speed > $max) {
                        $this->alert($rule, $vehicle, '',
                            "{$vehicle->display_name} iba a " . round($p->speed) . " km/h (límite {$max}).");
                        break;
                    }
                } elseif ($this->engineOn($p) && !$this->inWindow($rule, Carbon::parse($p->recorded_at))) {
                    $this->alert($rule, $vehicle, '',
                        "{$vehicle->display_name} está encendido fuera del horario permitido.");
                    break;
                }
            }
        }
    }

    /** Geocerca: el evento ya detectado por GeofenceDetectionService. */
    public function onGeofenceEvent(FleetGeofenceEvent $event): void
    {
        $vehicle = FleetVehicle::find($event->vehicle_id);
        if (!$vehicle || !$vehicle->client_id) {
            return;
        }
        $evaluator = app(RuleEvaluator::class);
        foreach ($this->rulesFor($vehicle, ['geocerca']) as $rule) {
            if ($evaluator->ruleMatches($rule, $event)) {
                $event->loadMissing('geofence');
                $verbo = $event->event_type === 'enter' ? 'entró a' : 'salió de';
                $this->alert($rule, $vehicle, 'ev' . $event->id,
                    "{$vehicle->display_name} {$verbo} " . ($event->geofence->name ?? 'una geocerca') . '.');
            }
        }
    }

    /** Sin conexión, documentos y mantenimiento: barrido periódico (comando). */
    public function sweep(): int
    {
        $n = 0;
        $rules = FleetGeofenceRule::where('origen', 'cliente')->where('active', true)
            ->whereIn('tipo', ['sin_conexion', 'documento', 'mantenimiento'])->get();

        foreach ($rules as $rule) {
            $vehicles = FleetVehicle::where('client_id', $rule->client_id)
                ->when(!empty($rule->vehicle_ids), fn ($q) => $q->whereIn('id', $rule->vehicle_ids))->get();

            foreach ($vehicles as $v) {
                foreach ($this->checkSweep($rule, $v) as [$key, $msg]) {
                    $n += $this->alert($rule, $v, $key, $msg) ? 1 : 0;
                }
            }
        }
        return $n;
    }

    /** @return array<int,array{0:string,1:string}> */
    private function checkSweep(FleetGeofenceRule $rule, FleetVehicle $v): array
    {
        $out = [];
        $name = $v->display_name;
        if ($rule->tipo === 'sin_conexion') {
            $min = (int) ($rule->params['minutos'] ?? 0);
            $last = FleetDevice::where('vehicle_id', $v->id)->max('last_seen_at');
            if ($min > 0 && $last && Carbon::parse($last)->lt(now()->subMinutes($min))) {
                $out[] = ['', "{$name} lleva más de {$min} min sin reportar su GPS (último: " . Carbon::parse($last)->format('d/m H:i') . ').'];
            }
        } elseif ($rule->tipo === 'documento') {
            $dias = (int) ($rule->params['dias_antes'] ?? 0);
            $docs = FleetDocument::where('vehicle_id', $v->id)->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '>=', today())
                ->whereDate('expiration_date', '<=', today()->addDays($dias))->get();
            foreach ($docs as $d) {
                $out[] = ['doc' . $d->id, \App\Modules\Addons\Flotas\Mail\FleetDocumentAlertMail::typeLabel($d->document_type)
                    . " de {$name} vence el " . $d->expiration_date->format('d/m/Y') . '.'];
            }
        } elseif ($rule->tipo === 'mantenimiento') {
            $kmAntes = (int) ($rule->params['km_antes'] ?? 0);
            $diasAntes = (int) ($rule->params['dias_antes'] ?? 0);
            foreach (app(ServicePredictionService::class)->predict($v) as $p) {
                $porKm = $kmAntes && $p['km_remaining'] !== null && $p['km_remaining'] <= $kmAntes;
                $porDias = $diasAntes && $p['days_remaining'] !== null && $p['days_remaining'] <= $diasAntes;
                if ($porKm || $porDias) {
                    $out[] = ['mant-' . $p['type'], "{$name} está por requerir mantenimiento ({$p['type']})."];
                }
            }
        }
        return $out;
    }

    private function rulesFor(FleetVehicle $vehicle, array $tipos): Collection
    {
        return FleetGeofenceRule::where('origen', 'cliente')->where('active', true)
            ->where('client_id', $vehicle->client_id)->whereIn('tipo', $tipos)->get()
            ->filter(fn ($r) => empty($r->vehicle_ids) || in_array($vehicle->id, $r->vehicle_ids));
    }

    private function engineOn(FleetPosition $p): bool
    {
        return $p->ignition === null ? (float) $p->speed > 0 : (bool) $p->ignition;
    }

    /** Dentro de días y ventana permitidos (time_from/time_to y days_of_week de la regla). */
    private function inWindow(FleetGeofenceRule $rule, Carbon $at): bool
    {
        if (!empty($rule->days_of_week) && !in_array($at->dayOfWeekIso, $rule->days_of_week)) {
            return false;
        }
        if ($rule->time_from && $rule->time_to) {
            $t = $at->format('H:i:s');
            $from = substr_count($rule->time_from, ':') === 1 ? $rule->time_from . ':00' : $rule->time_from;
            $to = substr_count($rule->time_to, ':') === 1 ? $rule->time_to . ':00' : $rule->time_to;
            return $from <= $to ? ($t >= $from && $t <= $to) : ($t >= $from || $t <= $to);
        }
        return true;
    }

    /** Registra y entrega la alerta si no hay otra reciente (anti-spam). */
    private function alert(FleetGeofenceRule $rule, FleetVehicle $vehicle, string $key, string $mensaje): bool
    {
        // Ventana configurable (config/flotas.php); nunca menor al enfriamiento propio del tipo.
        $cool = max(self::COOLDOWN[$rule->tipo] ?? 60,
            (int) config('flotas.alertas_cliente.ventana_antirepeticion_min', 15));
        // Geocerca: cada evento trae su propia llave; la misma regla+unidad igual no se repite en la ventana.
        $repetida = DB::table('fleet_client_alerts')
            ->where('rule_id', $rule->id)->where('vehicle_id', $vehicle->id)
            ->when($rule->tipo !== 'geocerca', fn ($q) => $q->where('subject_key', $key))
            ->where('created_at', '>=', now()->subMinutes($cool))->exists();
        if ($repetida) {
            return false;
        }

        $alertId = DB::table('fleet_client_alerts')->insertGetId([
            'rule_id' => $rule->id, 'vehicle_id' => $vehicle->id, 'client_id' => $vehicle->client_id,
            'tipo' => $rule->tipo, 'subject_key' => $key, 'mensaje' => mb_substr($mensaje, 0, 500),
            'entregas' => json_encode([]), 'created_at' => now(),
        ]);

        $entregas = $this->deliver($rule, $vehicle, $mensaje, $alertId);
        DB::table('fleet_client_alerts')->where('id', $alertId)->update(['entregas' => json_encode($entregas)]);
        return true;
    }

    /** Contactos de alerta activos del cliente; sin ninguno, el contacto principal de su ficha. */
    private function contactos(FleetVehicle $vehicle, string $tipo): Collection
    {
        $contactos = FleetClientAlertContact::active()->forClient((int) $vehicle->client_id)->get();
        if ($contactos->isEmpty()) {
            $cmi = ClientMainInformation::where('client_id', $vehicle->client_id)->first();
            $contactos = collect([new FleetClientAlertContact([
                'client_id' => $vehicle->client_id, 'nombre' => $cmi->name ?? null,
                'email' => $cmi->email ?? null, 'telefono' => $cmi->phone ?? null,
                'es_principal' => true, 'active' => true,
            ])]);
        }
        return $contactos->filter(fn ($c) => $c->recibeTipo($tipo))->values();
    }

    private function deliver(FleetGeofenceRule $rule, FleetVehicle $vehicle, string $mensaje, int $alertId): array
    {
        $clientId = (int) $vehicle->client_id;
        $companyId = $clientId ?: 1;
        $res = [];
        $hora = now()->format('H:i');

        $registrar = function (?FleetClientAlertContact $c, string $ch, ?string $dest, string $status, ?string $motivo) use (&$res, $rule, $vehicle, $alertId, $clientId) {
            FleetClientAlertDelivery::create([
                'client_id' => $clientId, 'contact_id' => $c?->id, 'alert_id' => $alertId,
                'rule_id' => $rule->id, 'vehicle_id' => $vehicle->id, 'channel' => $ch,
                'destination' => $dest, 'status' => $status, 'motivo' => $motivo ? mb_substr($motivo, 0, 190) : null,
            ]);
            $res[] = array_filter(['channel' => $ch, 'status' => $status, 'destination' => $dest, 'error' => $motivo]);
        };

        $enviarCorreo = function (?FleetClientAlertContact $c, string $dest) use ($rule, $mensaje, $registrar) {
            try {
                Correo::enviar('flotas.correo', $dest, new FleetClientRuleAlertMail($rule->name, $mensaje));
                $registrar($c, 'email', $dest, 'sent', null);
            } catch (\Throwable $e) {
                Log::warning("Flotas ClientRule {$rule->id}: email falló: {$e->getMessage()}");
                $registrar($c, 'email', $dest, 'failed', $e->getMessage());
            }
        };

        foreach ($this->contactos($vehicle, $rule->tipo) as $c) {
            if ($c->enSilencio($hora)) {
                $registrar($c, '*', null, 'skipped', 'silencio');
                continue;
            }
            $canales = ($c->channels ?? []) ? array_values(array_intersect($rule->channels ?? [], $c->channels)) : ($rule->channels ?? []);
            $email = trim((string) $c->email);
            $tel = preg_replace('/[^0-9]/', '', (string) $c->telefono);

            foreach ($canales as $ch) {
                if ($ch === 'email') {
                    $email === '' ? $registrar($c, $ch, null, 'skipped', 'sin email') : $enviarCorreo($c, $email);
                } elseif ($ch === 'whatsapp') {
                    if ($tel === '') { $registrar($c, $ch, null, 'skipped', 'sin teléfono'); continue; }
                    $uso = FleetClientWhatsappUsage::delMes($clientId);
                    if ($uso->topeAlcanzado()) {
                        $registrar($c, $ch, $tel, 'skipped', 'tope');
                        // Cae a correo (si ese canal no iba ya) y avisa del tope UNA vez por mes (queda en la bitácora).
                        if (! $uso->aviso_tope_at) {
                            $uso->update(['aviso_tope_at' => now()]);
                            $registrar($c, $ch, $tel, 'skipped', 'aviso único: tope WhatsApp alcanzado');
                        }
                        if ($email !== '' && ! in_array('email', $canales, true)) {
                            $enviarCorreo($c, $email);
                        }
                        continue;
                    }
                    try {
                        Servicios::whatsapp('flotas.reglas', $companyId)->sendText($tel, "⚠️ {$rule->name}: {$mensaje}");
                        $uso->increment('enviados');
                        $registrar($c, $ch, $tel, 'sent', null);
                    } catch (\Throwable $e) {
                        Log::warning("Flotas ClientRule {$rule->id}: whatsapp falló: {$e->getMessage()}");
                        $registrar($c, $ch, $tel, 'failed', $e->getMessage());
                    }
                }
            }
        }
        return $res;
    }
}
