<?php

namespace App\Support;

/**
 * Formateo de despliegue (item roadmap #155): el dato canónico en BD vive SIN
 * padding (ver ClientMainInformation::normalizeUserNumber). Este helper solo
 * es para VISTAS/REPORTES que quieran mostrar el número de cliente con ceros
 * a la izquierda — no toca el dato guardado.
 */
class ClienteNumeroFormatter
{
    public static function pad($value, int $length = 6): string
    {
        $value = (string) $value;
        if ($value === '' || !ctype_digit($value)) {
            return $value;
        }
        return str_pad($value, $length, '0', STR_PAD_LEFT);
    }
}
