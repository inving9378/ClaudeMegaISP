<?php

namespace App\Services\Ipv6\Drivers;

use App\Services\Ipv6\Contracts\Ipv6CommandDriverInterface;

/**
 * Se usa cuando la versión de RouterOS no coincide con ninguna familia conocida
 * (6.x, 7.0-7.12, 7.13+). Ninguna operación está soportada: TODAS devuelven
 * advertencia y ningún comando, para no arriesgar sintaxis inválida contra una
 * versión que no se pudo identificar.
 */
class FallbackDriver implements Ipv6CommandDriverInterface
{
    public function __construct(private readonly string $versionSolicitada)
    {
    }

    public function nombre(): string
    {
        return "Fallback (versión no reconocida: {$this->versionSolicitada})";
    }

    public function capacidades(): array
    {
        return [
            'crearSegmento' => false,
            'crearPool' => false,
            'asignarPerfilPPP' => false,
            'crearBlackhole' => false,
            'crearListaFirewall' => false,
            'deprecarPrefijo' => false,
            'eliminarPorMarcador' => false,
        ];
    }

    public function crearSegmento(string $prefijo, string $interfaz, ?string $comentario = null): array
    {
        return $this->advertencia('crearSegmento');
    }

    public function crearPool(string $nombrePool, string $prefijo, int $longitudDelegacion): array
    {
        return $this->advertencia('crearPool');
    }

    public function asignarPerfilPPP(string $perfil, string $nombrePool): array
    {
        return $this->advertencia('asignarPerfilPPP');
    }

    public function crearBlackhole(string $prefijo, ?string $comentario = null): array
    {
        return $this->advertencia('crearBlackhole');
    }

    public function crearListaFirewall(string $lista, string $prefijo, ?string $comentario = null): array
    {
        return $this->advertencia('crearListaFirewall');
    }

    public function deprecarPrefijo(string $prefijo, ?string $comentario = null): array
    {
        return $this->advertencia('deprecarPrefijo');
    }

    public function eliminarPorMarcador(string $marcador): array
    {
        return $this->advertencia('eliminarPorMarcador');
    }

    private function advertencia(string $operacion): array
    {
        return [
            'comandos' => [],
            'advertencias' => [sprintf(
                'Versión de RouterOS no reconocida ("%s"): no se genera el comando de %s para evitar sintaxis inválida. '
                . 'Especifique una versión soportada (6.x, 7.0-7.12, 7.13+).',
                $this->versionSolicitada,
                $operacion
            )],
        ];
    }
}
