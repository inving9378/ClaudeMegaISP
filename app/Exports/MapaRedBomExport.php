<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * MR-25 Fase 4 (item roadmap #9990445) — BOM del proyecto en 2 hojas (cables / cajas).
 * Misma librería ya usada por el resto del sistema (Maatwebsite\Excel, ver
 * app/Exports/DocumentacionApartadoExport.php).
 */
class MapaRedBomExport implements WithMultipleSheets
{
    public function __construct(private readonly array $bom)
    {
    }

    public function sheets(): array
    {
        return [
            new MapaRedBomCablesSheet($this->bom['cables'] ?? []),
            new MapaRedBomCajasSheet($this->bom['cajas'] ?? []),
        ];
    }
}
