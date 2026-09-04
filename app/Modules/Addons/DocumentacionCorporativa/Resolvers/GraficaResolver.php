<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Resolvers;

use App\Modules\Addons\DocumentacionCorporativa\Contracts\ResultadoConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;

/**
 * Igual que `SistemaResolver` —misma fuente, mismo registro, mismo aislamiento
 * de errores— pero la vista pinta series en vez de tabla: antigüedad de saldos,
 * ingresos por periodo, estructura accionaria.
 *
 * Hereda a propósito: si la resolución de fuentes divergiera en dos copias,
 * una gráfica y su tabla podrían contar cosas distintas sobre el mismo dato.
 */
class GraficaResolver extends SistemaResolver
{
    protected function vista(): string
    {
        return 'dc-concepto-grafica';
    }
}
