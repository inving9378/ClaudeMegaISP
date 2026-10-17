<?php

namespace App\Observers\Facturacion;

use App\Support\PlazoContrato;

/**
 * FR-03 (#537): los meses del plazo Especial solo se guardan si la duración es «Especial».
 * Cliente (las dos clases de ClientMainInformation) y prospecto (CrmMainInformation).
 */
class PlazoContratoObserver
{
    public function saving($modelo): void
    {
        PlazoContrato::normalizar($modelo);
    }
}
