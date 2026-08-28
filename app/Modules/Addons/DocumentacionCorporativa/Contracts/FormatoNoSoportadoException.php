<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Contracts;

use RuntimeException;

/**
 * El formato de exportación pedido no está implementado todavía.
 *
 * En la Fase 0 se soportan `csv` y `pdf`. `xlsx` llega en la Fase 1, junto con
 * `app/Exports/` (hoy `maatwebsite/excel` está instalado pero el proyecto no
 * tiene ningún patrón de exportación a Excel del que copiarse).
 */
class FormatoNoSoportadoException extends RuntimeException
{
    public static function para(string $formato): self
    {
        return new self("Formato de exportación no soportado: '{$formato}'. Disponibles: csv, pdf.");
    }
}
