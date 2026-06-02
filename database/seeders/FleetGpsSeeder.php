<?php

namespace Database\Seeders;

use App\Modules\Addons\Flotas\Models\FleetDevice;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\FleetPositionService;
use App\Modules\Addons\Flotas\Services\Gps\Drivers\MockDriver;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FleetGpsSeeder extends Seeder
{
    public function run(): void
    {
        $vehicle = FleetVehicle::orderBy('id')->first();
        if (!$vehicle) {
            $this->command->error('No hay vehículos. Crea uno antes de correr este seeder.');
            return;
        }

        // 1-2-3. Dispositivo mock + has_gps
        $device = FleetDevice::firstOrCreate(
            ['vehicle_id' => $vehicle->id],
            [
                'imei'         => 'MOCK' . str_pad((string) $vehicle->id, 11, '0', STR_PAD_LEFT),
                'brand'        => 'mock',
                'model'        => 'Simulador 24h',
                'sim_number'   => '5215500000000',
                'sim_carrier'  => 'Telcel',
                'status'       => 'active',
                'installed_at' => now(),
            ]
        );

        $vehicle->update([
            'has_gps'   => true,
            'gps_brand' => 'mock',
            'gps_model' => 'Simulador 24h',
            'gps_imei'  => $device->imei,
        ]);

        // 4. 24 horas de pings cada 30 segundos = 2880 posiciones
        $interval = 30;
        $count = (24 * 3600) / $interval; // 2880
        $startAt = Carbon::now()->subDay();

        $driver = new MockDriver();
        $positions = $driver->generateTrack(
            imei: $device->imei,
            startLat: MockDriver::DEFAULT_LAT,
            startLng: MockDriver::DEFAULT_LNG,
            count: (int) $count,
            intervalSeconds: $interval,
            startAt: $startAt,
        );

        $service = app(FleetPositionService::class);
        $saved = $service->saveBatch($positions, $vehicle->id, $device->id);

        // 5. Reporte
        $this->command->info("✅ {$saved} posiciones GPS simuladas para «{$vehicle->display_name}» (IMEI {$device->imei}).");
    }
}
