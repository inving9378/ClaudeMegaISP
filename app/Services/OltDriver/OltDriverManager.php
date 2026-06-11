<?php

namespace App\Services\OltDriver;

use App\Models\Olt;
use App\Services\OltDriver\Huawei\HuaweiDriver;
use Illuminate\Contracts\Container\Container;

/**
 * Resuelve el driver correcto para cada OLT según su columna `driver`.
 *
 * Consumidores que aún no son per-OLT deben seguir usando el binding global
 * OltDriverInterface::class (que sigue apuntando a SmartOltDriver). Solo migrar
 * al manager cuando el consumidor necesite operar contra una OLT específica.
 *
 * Palanca actualmente inerte: ninguna OLT tiene driver='huawei'.
 * El selector de driver en UI + permiso gestion-red.driver.change están
 * bloqueados hasta validar B1c (sesión Telnet contra MA5800-X7 real).
 */
class OltDriverManager
{
    public function __construct(private readonly Container $container) {}

    public function driverFor(Olt $olt): OltDriverInterface
    {
        return match($olt->driver) {
            Olt::DRIVER_SMARTOLT => $this->container->make(SmartOltDriver::class),
            Olt::DRIVER_HUAWEI   => $this->container->make(HuaweiDriver::class),
            default              => throw new UnknownOltDriverException($olt->driver),
        };
    }

    /**
     * Driver del binding global — para consumidores aún no migrados al manager.
     */
    public function default(): OltDriverInterface
    {
        return $this->container->make(OltDriverInterface::class);
    }
}
