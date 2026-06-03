<?php

namespace App\Modules\Addons\Flotas\Console;

use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Models\FleetPosition;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\GeofenceDetectionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

// Sub-fase 3.2 — Reprocesa el histórico de posiciones para (re)generar eventos de geocercas.
// Útil para probar con datos del MockDriver o recalcular si cambian las geocercas.
class ReprocessGeofencesCommand extends Command
{
    protected $signature = 'flotas:reprocess-geofences
                            {vehicle_id : ID del vehículo a reprocesar}
                            {--hours=24 : Horas hacia atrás a reprocesar}';

    protected $description = 'Reprocesa posiciones GPS y detecta entradas/salidas de geocercas';

    public function handle(GeofenceDetectionService $detection): int
    {
        $vehicleId = (int) $this->argument('vehicle_id');
        $hours     = max(1, (int) $this->option('hours'));

        $vehicle = FleetVehicle::find($vehicleId);
        if (!$vehicle) {
            $this->error("Vehículo {$vehicleId} no existe.");
            return self::FAILURE;
        }

        $since = Carbon::now()->subHours($hours);

        $positions = FleetPosition::where('vehicle_id', $vehicleId)
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')->orderBy('id')
            ->get();

        if ($positions->isEmpty()) {
            $this->warn("Sin posiciones para el vehículo {$vehicleId} en las últimas {$hours}h.");
            return self::SUCCESS;
        }

        // Idempotencia: borra eventos previos en el rango antes de reprocesar.
        $deleted = FleetGeofenceEvent::where('vehicle_id', $vehicleId)
            ->where('occurred_at', '>=', $since)
            ->delete();

        $detection->clearCache();
        $events = $detection->processBatch($positions);

        $enters = count(array_filter($events, fn($e) => $e['event_type'] === 'enter'));
        $exits  = count(array_filter($events, fn($e) => $e['event_type'] === 'exit'));

        $this->info("✅ Vehículo «{$vehicle->display_name}»: {$positions->count()} posiciones reprocesadas (últimas {$hours}h).");
        $this->line("   Eventos previos borrados: {$deleted}");
        $this->line("   Eventos creados: " . count($events) . " ({$enters} entradas, {$exits} salidas)");

        return self::SUCCESS;
    }
}
