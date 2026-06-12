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
 * ── FLIP MANUAL — cómo activar el driver Huawei en una OLT ──────────────────
 *
 * Paso 1 — verificar que la OLT esté en la BD y anotar su id:
 *   DB::table('olts')->where('name','MEGANETWORKX7')->value('id');
 *
 * Paso 2 — flip (atómico, tarda <1ms):
 *   DB::table('olts')->where('id', $id)->update(['driver' => 'huawei']);
 *
 * Paso 3 — rollback si hay problema:
 *   DB::table('olts')->where('id', $id)->update(['driver' => 'smartolt']);
 *
 * Paso 4 — verificar que OltDriverManager resuelve correcto (tinker):
 *   $olt = \App\Models\Olt::find($id);
 *   app(\App\Services\OltDriver\OltDriverManager::class)->driverFor($olt)->getName();
 *   // → debe imprimir 'Huawei'
 *
 * Pre-requisitos antes del flip a producción:
 *   - B3b validado (parsers contra puerto real cargado)
 *   - B3d sync cron activo (gestionred:sync-huawei corriendo cada 10min)
 *   - B3d validación Irving: getOnusByOlt + getOnusSignals en green
 *   - Timeout Telnet en HuaweiTransport ≥ 360s para puertos cargados
 *   - Cache driver configurado (no 'array') para que Cache::lock funcione
 *
 * ─────────────────────────────────────────────────────────────────────────────
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
