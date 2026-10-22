<?php

namespace App\Support;

/**
 * ¿Este servidor es producción? Prod corre con APP_ENV=local por herencia, así que se reconoce
 * también porque recibe versiones de GitHub (updates.enabled). Todo lo demás es entorno de pruebas.
 */
final class Entorno
{
    public static function esProduccion(): bool
    {
        return app()->environment('production') || (bool) config('updates.enabled');
    }
}
