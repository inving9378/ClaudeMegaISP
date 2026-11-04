<?php

namespace Tests\Unit\Metricas;

use App\Services\Metricas\RouterOs\RouterOsApi;
use RuntimeException;

/**
 * API de RouterOS falsa para las pruebas del item #988: contesta desde un JSON de fixtures,
 * aplica los filtros «campo = valor» (unidos con O) como lo haría el router y cuenta las
 * llamadas por ruta. /ping se busca por la IP; una IP que no está en el fixture es un error del
 * router (como un !trap).
 */
final class FixtureRouterOsApi implements RouterOsApi
{
    /** @var array<string, int> */
    public array $llamadas = [];

    private array $datos;

    public function __construct(string $archivo)
    {
        $this->datos = json_decode((string) file_get_contents(__DIR__ . '/fixtures/' . $archivo), true, flags: JSON_THROW_ON_ERROR);
    }

    public function leer(string $ruta, array $argumentos = [], array $propiedades = [], array $donde = []): array
    {
        $this->llamadas[$ruta] = ($this->llamadas[$ruta] ?? 0) + 1;

        if ($ruta === '/ping') {
            $ip = $argumentos['address'] ?? '';
            $filas = $this->datos['/ping'][$ip] ?? throw new RuntimeException("RouterOS respondió con error en /ping: no hay ruta a {$ip}");
        } else {
            $filas = $this->datos[$ruta] ?? throw new RuntimeException("RouterOS respondió con error en {$ruta}: no such command");
        }

        // monitor-traffic contesta solo las interfaces que se le pasan (separadas por coma).
        if ($ruta === '/interface/monitor-traffic') {
            $nombres = explode(',', (string) ($argumentos['interface'] ?? ''));
            $filas = array_values(array_filter($filas, fn ($f) => in_array($f['name'] ?? null, $nombres, true)));
        }

        if ($donde !== []) {
            $filas = array_values(array_filter($filas, function ($f) use ($donde) {
                foreach ($donde as [$campo, $valor]) {
                    if (($f[$campo] ?? null) === $valor) {
                        return true;
                    }
                }

                return false;
            }));
        }

        if ($propiedades === []) {
            return $filas;
        }

        return array_map(fn ($f) => array_combine($propiedades, array_map(fn ($p) => $f[$p] ?? null, $propiedades)), $filas);
    }

    public function cerrar(): void
    {
    }
}
