<?php

namespace App\Support\Seguridad;

use App\Support\Entorno;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Seguridad V1.64 (2026-10-22): reglas comunes de los seeders que crean cuentas demo o de prueba.
 * El repo es público: una contraseña escrita en el código queda expuesta para siempre.
 */
final class CuentasDemo
{
    /**
     * Producción no crea cuentas demo. Se reconoce por APP_ENV=production o porque recibe
     * versiones de GitHub (updates.enabled): prod corre con APP_ENV=local por herencia.
     */
    public static function servidorProtegido(): bool
    {
        return Entorno::esProduccion();
    }

    /** Contraseña nueva para una cuenta demo: aleatoria, nunca fija en el código. */
    public static function contrasena(): string
    {
        return Str::password(20, symbols: false);
    }

    /** Detiene el seeder en un servidor protegido. Devuelve true si debe salir. */
    public static function detenerSiEsProtegido(?Command $consola): bool
    {
        if (! self::servidorProtegido()) {
            return false;
        }
        $consola?->error('No se crean cuentas demo en producción ni en un servidor que recibe versiones.');

        return true;
    }

    /** Muestra la contraseña una sola vez; no queda en ningún archivo. */
    public static function mostrarUnaVez(?Command $consola, string $cuenta, string $contrasena): void
    {
        $consola?->warn("Contraseña de {$cuenta} (se muestra una sola vez, guárdala donde corresponde): {$contrasena}");
    }
}
