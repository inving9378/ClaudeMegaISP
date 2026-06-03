<?php

namespace App\Modules\Addons\Flotas\Services\Gps;

use App\Modules\Addons\Flotas\Services\Gps\Drivers\RuptelaDriver;

/**
 * Sub-fase 2.3a — Identifica la marca del dispositivo por su handshake/primer
 * paquete y devuelve el driver correspondiente. Sumar Concox/GT06 a futuro =
 * añadir su driver al arreglo, sin tocar el listener.
 */
class DeviceFactory
{
    /** @var class-string<GpsDriverInterface>[] */
    private const DRIVERS = [
        RuptelaDriver::class,
        // ConcoxDriver::class,   // futuro
        // Gt06Driver::class,     // futuro
    ];

    public static function fromHandshake(string $firstBytes): ?GpsDriverInterface
    {
        foreach (self::DRIVERS as $driverClass) {
            $driver = app($driverClass);
            if ($driver->supports($firstBytes)) {
                return $driver;
            }
        }
        return null;
    }
}
