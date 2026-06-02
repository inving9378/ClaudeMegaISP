<?php

namespace App\Modules\Addons\Flotas\Services\Gps\Drivers;

use App\Modules\Addons\Flotas\Services\Gps\GpsDriverInterface;
use App\Modules\Addons\Flotas\Services\Gps\Position;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Driver simulador. No habla con hardware: genera trayectos realistas para
 * poblar la BD y probar la UI antes de tener un GPS físico.
 */
class MockDriver implements GpsDriverInterface
{
    // Centro de CDMX como punto de partida por defecto.
    public const DEFAULT_LAT = 19.4326;
    public const DEFAULT_LNG = -99.1332;

    public function name(): string
    {
        return 'mock';
    }

    public function supports(string $handshake): bool
    {
        return str_contains(strtolower($handshake), 'mock');
    }

    /**
     * Acepta un payload JSON simulado (un objeto o arreglo de objetos) y lo
     * convierte a Position[]. Para datos no-JSON devuelve vacío.
     */
    public function parse(string $binaryData): array
    {
        $decoded = json_decode($binaryData, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = isset($decoded['lat']) ? [$decoded] : $decoded;
        $out = [];
        foreach ($rows as $r) {
            if (!isset($r['imei'], $r['lat'], $r['lng'])) {
                continue;
            }
            $out[] = new Position(
                imei: (string) $r['imei'],
                lat: (float) $r['lat'],
                lng: (float) $r['lng'],
                speed: (float) ($r['speed'] ?? 0),
                heading: (float) ($r['heading'] ?? 0),
                altitude: isset($r['altitude']) ? (float) $r['altitude'] : null,
                satellites: isset($r['satellites']) ? (int) $r['satellites'] : null,
                hdop: isset($r['hdop']) ? (float) $r['hdop'] : null,
                ignition: isset($r['ignition']) ? (bool) $r['ignition'] : null,
                battery: isset($r['battery']) ? (float) $r['battery'] : null,
                recorded_at: isset($r['recorded_at']) ? Carbon::parse($r['recorded_at']) : null,
            );
        }

        return $out;
    }

    /**
     * Genera un trayecto simulado.
     *
     * @return Position[]
     */
    public function generateTrack(
        string $imei,
        ?float $startLat = null,
        ?float $startLng = null,
        int $count = 120,
        int $intervalSeconds = 30,
        ?CarbonInterface $startAt = null
    ): array {
        $lat = $startLat ?? self::DEFAULT_LAT;
        $lng = $startLng ?? self::DEFAULT_LNG;
        $heading = mt_rand(0, 359);
        $battery = 100.0;
        $startAt ??= Carbon::now()->subSeconds($count * $intervalSeconds);

        $positions = [];
        for ($i = 0; $i < $count; $i++) {
            $stopped = mt_rand(1, 100) <= 10; // 10% de paradas

            if ($stopped) {
                $speed = 0.0;
                $distance = 0.0;
            } else {
                $speed = (float) mt_rand(5, 80);
                $distance = (float) mt_rand(10, 100); // metros por ping
                // El rumbo deriva suavemente para que la polilínea parezca una ruta.
                $heading = ($heading + mt_rand(-30, 30) + 360) % 360;
            }

            // Desplazamiento geográfico según rumbo y distancia.
            $rad = deg2rad($heading);
            $latRad = deg2rad($lat);
            $lat += ($distance * cos($rad)) / 111320;
            $lng += ($distance * sin($rad)) / (111320 * max(0.000001, cos($latRad)));

            // La batería drena lentamente; se recarga al ralentí encendido.
            $battery = max(20.0, $battery - (mt_rand(0, 2) / 100));

            $positions[] = new Position(
                imei: $imei,
                lat: $lat,
                lng: $lng,
                speed: $speed,
                heading: (float) $heading,
                altitude: 2240.0 + mt_rand(-30, 30),
                satellites: mt_rand(6, 14),
                hdop: mt_rand(60, 200) / 100,
                ignition: $stopped ? (mt_rand(1, 100) <= 70) : true,
                battery: round($battery, 2),
                recorded_at: (clone $startAt)->addSeconds($i * $intervalSeconds),
            );
        }

        return $positions;
    }
}
