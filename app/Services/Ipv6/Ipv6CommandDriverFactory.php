<?php

namespace App\Services\Ipv6;

use App\Services\Ipv6\Contracts\Ipv6CommandDriverInterface;
use App\Services\Ipv6\Drivers\FallbackDriver;
use App\Services\Ipv6\Drivers\RouterOs6xDriver;
use App\Services\Ipv6\Drivers\RouterOs70Driver;
use App\Services\Ipv6\Drivers\RouterOs713Driver;

/**
 * Resuelve una cadena de versión de RouterOS ("7.14.2", "6.49.6", ...) al driver
 * de la familia correspondiente. Versión no reconocida/no parseable -> FallbackDriver
 * (advertencia, sin comandos) en vez de adivinar.
 */
class Ipv6CommandDriverFactory
{
    public static function paraVersion(string $version): Ipv6CommandDriverInterface
    {
        $partes = self::parsearVersion($version);
        if ($partes === null) {
            return new FallbackDriver($version);
        }

        [$mayor, $menor] = $partes;

        if ($mayor === 7 && $menor >= 13) {
            return new RouterOs713Driver();
        }
        if ($mayor === 7) {
            return new RouterOs70Driver();
        }
        if ($mayor === 6) {
            return new RouterOs6xDriver();
        }

        return new FallbackDriver($version);
    }

    /** @return array{0: int, 1: int}|null */
    private static function parsearVersion(string $version): ?array
    {
        if (!preg_match('/^\s*(\d+)\.(\d+)(?:\.\d+)?/', $version, $m)) {
            return null;
        }

        return [(int) $m[1], (int) $m[2]];
    }
}
