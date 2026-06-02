<?php

namespace App\Modules\Addons\Flotas\Console;

use App\Modules\Addons\Flotas\Models\FleetDevice;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\FleetPositionService;
use App\Modules\Addons\Flotas\Services\Gps\Drivers\MockDriver;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SimulateGpsCommand extends Command
{
    protected $signature = 'flotas:simulate-gps
                            {vehicle_id : ID del vehículo a simular}
                            {--interval=30 : Segundos entre pings}
                            {--duration=3600 : Duración total en segundos}';

    protected $description = 'Genera posiciones GPS simuladas (MockDriver) para un vehículo';

    public function handle(FleetPositionService $service): int
    {
        $vehicleId = (int) $this->argument('vehicle_id');
        $interval  = max(1, (int) $this->option('interval'));
        $duration  = max($interval, (int) $this->option('duration'));
        $count     = (int) floor($duration / $interval);

        $vehicle = FleetVehicle::find($vehicleId);
        if (!$vehicle) {
            $this->error("Vehículo {$vehicleId} no existe.");
            return self::FAILURE;
        }

        // Asegura un dispositivo mock para el vehículo.
        $device = FleetDevice::where('vehicle_id', $vehicleId)->first();
        if (!$device) {
            $device = FleetDevice::create([
                'vehicle_id'  => $vehicleId,
                'imei'        => 'MOCK' . str_pad((string) $vehicleId, 11, '0', STR_PAD_LEFT),
                'brand'       => 'mock',
                'model'       => 'Simulador',
                'sim_carrier' => 'Telcel',
                'status'      => 'active',
                'installed_at' => now(),
            ]);
            $vehicle->update(['has_gps' => true, 'gps_brand' => 'mock', 'gps_imei' => $device->imei]);
            $this->info("Dispositivo mock creado (IMEI {$device->imei}).");
        }

        // Continúa desde la última posición conocida, si existe.
        $last = $service->getLastPosition($vehicleId);
        $startLat = $last?->lat;
        $startLng = $last?->lng;
        $startAt  = $last?->recorded_at?->copy()->addSeconds($interval) ?? Carbon::now()->subSeconds($count * $interval);

        $driver = new MockDriver();
        $positions = $driver->generateTrack(
            imei: $device->imei,
            startLat: $startLat,
            startLng: $startLng,
            count: $count,
            intervalSeconds: $interval,
            startAt: $startAt,
        );

        $saved = $service->saveBatch($positions, $vehicleId, $device->id);

        $this->info("✅ {$saved} posiciones generadas para «{$vehicle->display_name}» (cada {$interval}s, {$duration}s totales).");
        return self::SUCCESS;
    }
}
