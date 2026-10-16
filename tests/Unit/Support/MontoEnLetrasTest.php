<?php

namespace Tests\Unit\Support;

use App\Support\MontoEnLetras;
use PHPUnit\Framework\TestCase;

/**
 * Importe con letra de la Prefactura (item #480). PHP puro: no arranca la app ni toca MySQL.
 */
class MontoEnLetrasTest extends TestCase
{
    /** @dataProvider casos */
    public function test_escribe_el_importe_en_letras($monto, string $esperado): void
    {
        $this->assertSame($esperado, MontoEnLetras::pesosMxn($monto));
    }

    public static function casos(): array
    {
        return [
            'ejemplo del spec'            => [350, 'TRESCIENTOS CINCUENTA PESOS 00/100 M.N.'],
            'cero'                        => [0, 'CERO PESOS 00/100 M.N.'],
            'solo centavos'               => [0.5, 'CERO PESOS 50/100 M.N.'],
            'un peso en singular'         => [1, 'UN PESO 00/100 M.N.'],
            'veintiún con acento'         => [21, 'VEINTIÚN PESOS 00/100 M.N.'],
            'dieciséis'                   => [16, 'DIECISÉIS PESOS 00/100 M.N.'],
            'treinta y un'                => [31, 'TREINTA Y UN PESOS 00/100 M.N.'],
            'cien exacto'                 => [100, 'CIEN PESOS 00/100 M.N.'],
            'ciento uno'                  => [101, 'CIENTO UN PESOS 00/100 M.N.'],
            'quinientos'                  => [500, 'QUINIENTOS PESOS 00/100 M.N.'],
            'factura real'                => ['449.00', 'CUATROCIENTOS CUARENTA Y NUEVE PESOS 00/100 M.N.'],
            'centavos'                    => [1234.56, 'MIL DOSCIENTOS TREINTA Y CUATRO PESOS 56/100 M.N.'],
            'mil sin un'                  => [1000, 'MIL PESOS 00/100 M.N.'],
            'veintiún mil'                => [21000, 'VEINTIÚN MIL PESOS 00/100 M.N.'],
            'cien mil'                    => [100000, 'CIEN MIL PESOS 00/100 M.N.'],
            'ciento un mil'               => [101001, 'CIENTO UN MIL UN PESOS 00/100 M.N.'],
            'un millón exacto lleva DE'   => [1000000, 'UN MILLÓN DE PESOS 00/100 M.N.'],
            'millones exactos llevan DE'  => [3000000, 'TRES MILLONES DE PESOS 00/100 M.N.'],
            'millón con resto sin DE'     => [2500000, 'DOS MILLONES QUINIENTOS MIL PESOS 00/100 M.N.'],
            'redondeo de centavos'        => [99.999, 'CIEN PESOS 00/100 M.N.'],
            'centavos de un decimal'      => ['10.1', 'DIEZ PESOS 10/100 M.N.'],
            'nulo como cero'              => [null, 'CERO PESOS 00/100 M.N.'],
        ];
    }
}
