<?php

namespace Database\Seeders;

use App\Modules\Addons\Flotas\Models\FleetGeofence;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use Illuminate\Database\Seeder;

class FleetGeofenceSeeder extends Seeder
{
    public function run(): void
    {
        $vehicle = FleetVehicle::orderBy('id')->first();
        if (!$vehicle) {
            $this->command->error('No hay vehículos. Crea uno antes de correr este seeder.');
            return;
        }

        // 1. Oficina central Meganet — cuadrado de ~200 m (CDMX, col. Centro)
        $cLat = 19.4338;
        $cLng = -99.1400;
        $d    = 0.0009; // ~100 m → lado ~200 m
        $office = FleetGeofence::updateOrCreate(
            ['name' => 'Oficina central Meganet', 'client_id' => null],
            [
                'description' => 'Perímetro de las oficinas centrales (demo).',
                'type'        => 'both',
                'color'       => '#2563eb',
                'active'      => true,
                'polygon'     => [
                    [$cLat + $d, $cLng - $d],
                    [$cLat + $d, $cLng + $d],
                    [$cLat - $d, $cLng + $d],
                    [$cLat - $d, $cLng - $d],
                ],
            ]
        );

        // 2. Zona de cobertura sur — polígono grande al sur de la ciudad
        $south = FleetGeofence::updateOrCreate(
            ['name' => 'Zona de cobertura sur', 'client_id' => null],
            [
                'description' => 'Área de servicio en la zona sur (demo).',
                'type'        => 'exit',
                'color'       => '#16a34a',
                'active'      => true,
                'polygon'     => [
                    [19.3700, -99.1900],
                    [19.3750, -99.1300],
                    [19.3350, -99.1250],
                    [19.3100, -99.1700],
                    [19.3400, -99.2050],
                ],
            ]
        );

        // 3. Asignar ambas al vehículo de prueba
        $office->vehicles()->syncWithoutDetaching([$vehicle->id]);
        $south->vehicles()->syncWithoutDetaching([$vehicle->id]);

        // 4. Geocerca "sobre el track" — se posiciona en la mediana de las posiciones reales
        //    del vehículo para GARANTIZAR que el track del MockDriver la cruce (Sub-fase 3.2).
        //    Se computa de los pings existentes porque el track del MockDriver es aleatorio.
        $lats = \App\Modules\Addons\Flotas\Models\FleetPosition::where('vehicle_id', $vehicle->id)
            ->orderBy('lat')->pluck('lat');
        $lngs = \App\Modules\Addons\Flotas\Models\FleetPosition::where('vehicle_id', $vehicle->id)
            ->orderBy('lng')->pluck('lng');

        if ($lats->count() >= 3) {
            $mLat = (float) $lats[intdiv($lats->count(), 2)];
            $mLng = (float) $lngs[intdiv($lngs->count(), 2)];
            $d = 0.008; // ~900 m de medio lado

            $track = FleetGeofence::updateOrCreate(
                ['name' => 'Corredor de prueba (track demo)', 'client_id' => null],
                [
                    'description' => 'Geocerca sobre la ruta del MockDriver para validar la detección (Sub-fase 3.2).',
                    'type'        => 'both',
                    'color'       => '#9333ea',
                    'active'      => true,
                    'polygon'     => [
                        [$mLat + $d, $mLng - $d],
                        [$mLat + $d, $mLng + $d],
                        [$mLat - $d, $mLng + $d],
                        [$mLat - $d, $mLng - $d],
                    ],
                ]
            );
            $track->vehicles()->syncWithoutDetaching([$vehicle->id]);
            $this->command->info("✅ 3 geocercas demo (incl. 'Corredor de prueba' sobre el track) asignadas a «{$vehicle->display_name}».");
        } else {
            $this->command->info("✅ 2 geocercas demo creadas y asignadas a «{$vehicle->display_name}». (Sin pings: corre FleetGpsSeeder para la 3ª sobre el track.)");
        }
    }
}
