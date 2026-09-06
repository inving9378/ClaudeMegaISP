<?php

namespace App\Modules\Addons\MapaRed\Support;

/**
 * D12 (épica MR-00): por defecto EIA/TIA-598 — 12 colores en orden estándar de la industria,
 * usados tanto para el color de buffer como para el color de hilo (se recorren de forma
 * independiente, ciclando cada 12).
 *
 * El selector alterno ABNT NBR 14771 y la configuración por catálogo quedan para cuando MR-08
 * (#944) exponga `mapared_tipo_cable.esquema_color`; mientras tanto este es el único esquema.
 */
class FiberColorScheme
{
    public static function eiaTia598(): array
    {
        return [
            'Azul', 'Naranja', 'Verde', 'Café', 'Gris', 'Blanco',
            'Rojo', 'Negro', 'Amarillo', 'Violeta', 'Rosa', 'Aqua',
        ];
    }
}
