<?php

namespace App\Modules\Addons\Flotas\Console;

use App\Modules\Addons\Flotas\Models\FleetGeofence;
use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Models\FleetPosition;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\Notifications\FleetNotificationDispatcher;
use Illuminate\Console\Command;

// Sub-fase 3.3 — Genera un evento sintético y dispara el dispatcher SÍNCRONO para validar canales.
class TestNotificationCommand extends Command
{
    protected $signature = 'flotas:test-notification {vehicle_id} {geofence_id} {event_type=enter}';

    protected $description = 'Crea un evento de geocerca sintético y despacha notificaciones (email + whatsapp)';

    public function handle(FleetNotificationDispatcher $dispatcher): int
    {
        $vehicleId  = (int) $this->argument('vehicle_id');
        $geofenceId = (int) $this->argument('geofence_id');
        $eventType  = $this->argument('event_type');

        if (!in_array($eventType, ['enter', 'exit'], true)) {
            $this->error("event_type debe ser 'enter' o 'exit'.");
            return self::FAILURE;
        }

        $vehicle  = FleetVehicle::find($vehicleId);
        $geofence = FleetGeofence::find($geofenceId);
        if (!$vehicle)  { $this->error("Vehículo {$vehicleId} no existe."); return self::FAILURE; }
        if (!$geofence) { $this->error("Geocerca {$geofenceId} no existe."); return self::FAILURE; }

        $lastPos = FleetPosition::where('vehicle_id', $vehicleId)->orderByDesc('recorded_at')->first();

        $event = FleetGeofenceEvent::create([
            'vehicle_id'  => $vehicleId,
            'geofence_id' => $geofenceId,
            'event_type'  => $eventType,
            'position_id' => $lastPos?->id,
            'occurred_at' => now(),
            'created_at'  => now(),
        ]);

        $this->info("Evento sintético #{$event->id} creado ({$eventType} · {$vehicle->display_name} · {$geofence->name}).");

        $results = $dispatcher->dispatch($event);

        if (empty($results)) {
            $this->warn('Sin destinatarios: ningún usuario tiene preferencias activas que matcheen este evento/tenant.');
            return self::SUCCESS;
        }

        $this->line('Resultados del despacho:');
        foreach ($results as $r) {
            $icon = $r['status'] === 'sent' ? '✅' : ($r['status'] === 'skipped' ? '⏭️' : '❌');
            $this->line("  {$icon} canal={$r['channel']} user={$r['user_id']} → {$r['status']}");
        }

        return self::SUCCESS;
    }
}
