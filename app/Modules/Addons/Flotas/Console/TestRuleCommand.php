<?php

namespace App\Modules\Addons\Flotas\Console;

use App\Models\User;
use App\Modules\Addons\Flotas\Models\FleetGeofence;
use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Models\FleetPosition;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\Notifications\RuleEvaluator;
use Illuminate\Console\Command;

// Sub-fase 3.4 — Crea un evento sintético y muestra la DECISIÓN del RuleEvaluator
// para un usuario (allow/deny + regla que coincide + canales), sin enviar nada.
class TestRuleCommand extends Command
{
    protected $signature = 'flotas:test-rule {user_id} {vehicle_id} {geofence_id} {event_type=enter} {--dispatch : Además despacha el evento (envía notificaciones reales)}';
    protected $description = 'Evalúa las reglas (Sub-fase 3.4) de un usuario contra un evento sintético';

    public function handle(RuleEvaluator $evaluator): int
    {
        $userId    = (int) $this->argument('user_id');
        $vehicleId = (int) $this->argument('vehicle_id');
        $geofence  = (int) $this->argument('geofence_id');
        $type      = $this->argument('event_type');

        $user = User::find($userId);
        if (!$user)                          { $this->error("Usuario {$userId} no existe."); return self::FAILURE; }
        if (!FleetVehicle::find($vehicleId)) { $this->error("Vehículo {$vehicleId} no existe."); return self::FAILURE; }
        if (!FleetGeofence::find($geofence)) { $this->error("Geocerca {$geofence} no existe."); return self::FAILURE; }
        if (!in_array($type, ['enter', 'exit'], true)) { $this->error("event_type debe ser enter|exit."); return self::FAILURE; }

        $lastPos = FleetPosition::where('vehicle_id', $vehicleId)->orderByDesc('recorded_at')->first();

        $event = FleetGeofenceEvent::create([
            'vehicle_id'  => $vehicleId,
            'geofence_id' => $geofence,
            'event_type'  => $type,
            'position_id' => $lastPos?->id,
            'occurred_at' => now(),
            'created_at'  => now(),
        ]);
        $event->loadMissing(['vehicle', 'geofence']);

        $eval = $evaluator->evaluate($user, $event);

        $this->info("Evento sintético #{$event->id} ({$type} · vehículo {$vehicleId} · geocerca {$geofence}) @ " . now()->format('Y-m-d H:i:s (D)'));
        $this->line('');
        $this->line('RuleEvaluator para ' . $user->email . ':');
        $this->line('  allowed       : ' . ($eval['allowed'] ? '✅ SÍ' : '❌ NO (filtrado)'));
        $this->line('  matched_rule  : ' . ($eval['matched_rule_id'] ?? '— (sin reglas → comportamiento 3.3)'));
        $this->line('  channels regla: ' . ($eval['channels'] ? implode(',', $eval['channels']) : '— (no filtra canales)'));

        if ($this->option('dispatch')) {
            $results = app(\App\Modules\Addons\Flotas\Services\Notifications\FleetNotificationDispatcher::class)->dispatch($event);
            $this->line('');
            $this->line('Despacho real:');
            foreach ($results as $r) {
                $this->line("  {$r['channel']} user={$r['user_id']} → {$r['status']}");
            }
            if (empty($results)) {
                $this->warn('  (sin destinatarios)');
            }
        } else {
            $this->line('');
            $this->comment('Solo evaluación (sin enviar). Usa --dispatch para despachar de verdad.');
        }

        return self::SUCCESS;
    }
}
