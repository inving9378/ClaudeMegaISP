<?php

namespace App\Services\OltDriver\Capabilities;

/**
 * Driver puede leer catálogos de la plataforma: zonas, tipos de ONU, ODBs.
 */
interface SupportsCatalog
{
    /**
     * Shape: ['success'=>bool, 'response'=>array<array{id:int|string, name:string}>]
     */
    public function listZones(): array;

    /**
     * Shape: ['success'=>bool, 'response'=>array<array{id:int|string, name:string, ...}>]
     */
    public function listOnuTypes(): array;

    /**
     * Shape: ['success'=>bool, 'response'=>array<array{id:int|string, name:string, ...}>]
     */
    public function listOdbs(): array;
}
