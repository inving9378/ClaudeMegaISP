<?php

namespace App\Support;

/**
 * Importe con letra en pesos mexicanos, como lo escribe un documento comercial:
 *   350      → "TRESCIENTOS CINCUENTA PESOS 00/100 M.N."
 *   1        → "UN PESO 00/100 M.N."
 *   21_000   → "VEINTIÚN MIL PESOS 00/100 M.N."
 *   1_000_000→ "UN MILLÓN DE PESOS 00/100 M.N."
 *
 * PHP puro a propósito (sin NumberFormatter de intl): no depende de extensiones del servidor
 * y deja fijas las reglas que importan aquí (apócope UN/VEINTIÚN, CIEN/CIENTO, "MIL" y no
 * "UN MIL", "DE PESOS" tras millones exactos). Usa el valor absoluto: los importes negativos
 * no tienen escritura propia en este documento.
 */
final class MontoEnLetras
{
    private const UNIDADES = [
        '', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE',
        'DIECIOCHO', 'DIECINUEVE', 'VEINTE', 'VEINTIÚN', 'VEINTIDÓS', 'VEINTITRÉS',
        'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE',
    ];

    private const DECENAS = [
        3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA',
        7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA',
    ];

    private const CENTENAS = [
        1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS',
        5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS',
        9 => 'NOVECIENTOS',
    ];

    public static function pesosMxn(float|int|string|null $monto): string
    {
        $centavosTotales = (int) round(abs((float) $monto) * 100);
        $enteros = intdiv($centavosTotales, 100);
        $centavos = $centavosTotales % 100;

        $moneda = $enteros === 1 ? 'PESO' : 'PESOS';
        if ($enteros >= 1_000_000 && $enteros % 1_000_000 === 0) {
            $moneda = 'DE ' . $moneda;
        }

        $palabras = $enteros === 0 ? 'CERO' : self::numero($enteros);

        return sprintf('%s %s %02d/100 M.N.', $palabras, $moneda, $centavos);
    }

    /** Entero positivo en letras (forma apocopada: "UN", "VEINTIÚN"). */
    public static function numero(int $n): string
    {
        if ($n >= 1_000_000) {
            $millones = intdiv($n, 1_000_000);
            $resto = $n % 1_000_000;
            $texto = $millones === 1 ? 'UN MILLÓN' : self::numero($millones) . ' MILLONES';

            return $resto ? $texto . ' ' . self::numero($resto) : $texto;
        }

        if ($n >= 1_000) {
            $miles = intdiv($n, 1_000);
            $resto = $n % 1_000;
            $texto = $miles === 1 ? 'MIL' : self::numero($miles) . ' MIL';

            return $resto ? $texto . ' ' . self::numero($resto) : $texto;
        }

        return self::centenas($n);
    }

    private static function centenas(int $n): string
    {
        if ($n === 100) {
            return 'CIEN';
        }

        $partes = [];
        if ($c = intdiv($n, 100)) {
            $partes[] = self::CENTENAS[$c];
        }
        if ($r = $n % 100) {
            $partes[] = self::decenas($r);
        }

        return implode(' ', $partes);
    }

    private static function decenas(int $n): string
    {
        if ($n < 30) {
            return self::UNIDADES[$n];
        }

        $u = $n % 10;

        return self::DECENAS[intdiv($n, 10)] . ($u ? ' Y ' . self::UNIDADES[$u] : '');
    }
}
