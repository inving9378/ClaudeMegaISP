<?php

namespace App\Support;

/**
 * CLABE interbancaria (18 dígitos): valida el dígito de control (pesos 3-7-1 sobre los primeros
 * 17, suma de cada producto mód 10; control = (10 − suma mód 10) mód 10). FAC-13, item #521.
 */
final class Clabe
{
    public static function valida(?string $clabe): bool
    {
        $clabe = preg_replace('/\s+/', '', (string) $clabe);
        if (! preg_match('/^\d{18}$/', $clabe)) {
            return false;
        }
        $pesos = [3, 7, 1];
        $suma = 0;
        for ($i = 0; $i < 17; $i++) {
            $suma += ((int) $clabe[$i] * $pesos[$i % 3]) % 10;
        }

        return (10 - ($suma % 10)) % 10 === (int) $clabe[17];
    }
}
