<?php

namespace App\Modules\Addons\Flotas\Services\Gps;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * DTO de una posición GPS, agnóstico del fabricante.
 * Los drivers (Ruptela, Concox, Mock, …) parsean su protocolo y devuelven
 * arreglos de Position; el resto del sistema solo conoce este formato.
 */
class Position
{
    public function __construct(
        public string $imei,
        public float $lat,
        public float $lng,
        public float $speed = 0.0,        // km/h
        public float $heading = 0.0,      // grados 0-360
        public ?float $altitude = null,   // metros
        public ?int $satellites = null,
        public ?float $hdop = null,
        public ?bool $ignition = null,
        public ?float $battery = null,    // %
        public ?CarbonInterface $recorded_at = null,
    ) {
        $this->recorded_at ??= Carbon::now();
    }

    /** Atributos listos para insertar en fleet_positions. */
    public function toAttributes(int $vehicleId, int $deviceId): array
    {
        $now = Carbon::now();

        return [
            'vehicle_id'  => $vehicleId,
            'device_id'   => $deviceId,
            'lat'         => round($this->lat, 7),
            'lng'         => round($this->lng, 7),
            'speed'       => round($this->speed, 2),
            'heading'     => round($this->heading, 2),
            'altitude'    => $this->altitude !== null ? round($this->altitude, 2) : null,
            'satellites'  => $this->satellites,
            'hdop'        => $this->hdop,
            'ignition'    => $this->ignition,
            'battery'     => $this->battery,
            'recorded_at' => $this->recorded_at,
            'received_at' => $now,
        ];
    }

    public function toArray(): array
    {
        return [
            'imei'        => $this->imei,
            'lat'         => $this->lat,
            'lng'         => $this->lng,
            'speed'       => $this->speed,
            'heading'     => $this->heading,
            'altitude'    => $this->altitude,
            'satellites'  => $this->satellites,
            'hdop'        => $this->hdop,
            'ignition'    => $this->ignition,
            'battery'     => $this->battery,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
        ];
    }
}
