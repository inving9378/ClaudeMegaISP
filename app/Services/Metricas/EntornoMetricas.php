<?php

namespace App\Services\Metricas;

use App\Support\Red\SoloLectura;

/**
 * ¿Este sistema es producción, para el monitoreo de dedicados? (item #988). Lo usa SOLO el colector;
 * RouterConnection sigue decidiendo por su lado para el resto del sistema.
 *
 * Producción = APP_ENV production O una base listada en metricas.bases_produccion: un APP_ENV mal
 * puesto no debe mandar al colector de producción a otro router. Fuera de producción solo se conecta
 * al router de laboratorio (MIKROTIK_DEV_*); sin laboratorio, a ninguno.
 */
final class EntornoMetricas
{
    public static function esProduccion(): bool
    {
        $conexion = config('database.default');

        return self::decidir(
            (string) config('app.env'),
            (string) config("database.connections.{$conexion}.database"),
            (array) config('metricas.bases_produccion', [])
        );
    }

    /** @param  string[]  $basesProduccion */
    public static function decidir(string $appEnv, string $base, array $basesProduccion): bool
    {
        return $appEnv === 'production' || in_array($base, $basesProduccion, true);
    }

    public static function hayLaboratorio(): bool
    {
        return self::laboratorioCompleto((array) config('metricas.laboratorio', []));
    }

    public static function laboratorioCompleto(array $lab): bool
    {
        return trim((string) ($lab['host'] ?? '')) !== ''
            && trim((string) ($lab['usuario'] ?? '')) !== ''
            && (string) ($lab['password'] ?? '') !== '';
    }

    /** METRICAS_DRIVER si está definido; si no, el real donde hay a qué conectarse y el simulado en DEV. */
    public static function driverPorDefecto(): string
    {
        return (string) (config('metricas.driver') ?: (self::esProduccion() || self::hayLaboratorio() || SoloLectura::routersRealesEnDev() ? 'mikrotik' : 'simulado'));
    }
}
