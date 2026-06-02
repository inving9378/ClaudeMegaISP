<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Hash;

/**
 * Servicio central de contraseñas. Unifica la verificación durante la
 * transición del esquema legacy `base64_encode(plano)` (reversible, riesgo
 * LFPDPPP) al hashing real con bcrypt.
 *
 * - `check()` acepta AMBOS formatos: si el valor almacenado ya es bcrypt usa
 *   `Hash::check()`; si sigue siendo base64 legacy compara con
 *   `base64_encode()` (comparación constante con `hash_equals`).
 * - `make()` siempre produce bcrypt — es lo único que debe escribirse de aquí
 *   en adelante.
 * - `needsRehash()` indica si un valor almacenado todavía es legacy y debe
 *   re-hashearse (upgrade-on-login o backfill masivo).
 *
 * Esto permite migrar sin downtime: los usuarios ya migrados validan por
 * bcrypt, los que aún no se auto-actualizan al primer login correcto, y el
 * comando `auth:rehash-passwords` cubre a los que nunca se loguean.
 */
class PasswordService
{
    /** Genera un hash bcrypt. Único método de escritura permitido. */
    public static function make(string $plain): string
    {
        return Hash::make($plain);
    }

    /**
     * Verifica una contraseña en claro contra el valor almacenado,
     * aceptando tanto bcrypt como el legacy base64.
     */
    public static function check(?string $plain, ?string $stored): bool
    {
        if ($plain === null || $stored === null || $stored === '') {
            return false;
        }

        if (self::isHashed($stored)) {
            return Hash::check($plain, $stored);
        }

        // Legacy: el "hash" era base64_encode(plano), reversible.
        return hash_equals($stored, base64_encode($plain));
    }

    /** ¿El valor almacenado ya es un hash bcrypt ($2a/$2b/$2y)? */
    public static function isHashed(?string $stored): bool
    {
        return is_string($stored) && preg_match('/^\$2[aby]\$/', $stored) === 1;
    }

    /** ¿El valor almacenado sigue en formato legacy y debe re-hashearse? */
    public static function needsRehash(?string $stored): bool
    {
        return ! self::isHashed($stored);
    }

    /**
     * Devuelve la contraseña en claro SÓLO si el valor almacenado sigue en
     * legacy base64 (reversible). Para bcrypt devuelve null — es irreversible.
     * Sirve para la feature transitoria "ver contraseña" del admin, que debe
     * desaparecer conforme se complete la migración.
     */
    public static function legacyPlain(?string $stored): ?string
    {
        if ($stored === null || $stored === '' || self::isHashed($stored)) {
            return null;
        }

        $plain = base64_decode($stored, true);

        return ($plain !== false && base64_encode($plain) === $stored) ? $plain : null;
    }
}
