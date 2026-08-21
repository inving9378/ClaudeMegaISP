<?php

namespace App\Services\Ipv6\Drivers;

use App\Services\Ipv6\Contracts\Ipv6CommandDriverInterface;

/**
 * Base compartida por las familias RouterOS reales (6.x, 7.0-7.12, 7.13+).
 * La sintaxis base de estas operaciones es estable entre esas tres familias
 * — la única diferencia documentada (incidente #811 del 18/08/2026) es
 * `eliminarPorMarcador()` en RouterOS 7.13+, que sobreescribe el método aquí.
 */
abstract class AbstractRouterOsDriver implements Ipv6CommandDriverInterface
{
    public const MARCADOR_DEFECTO = 'MgNet-IPv6';

    /** @return array<string, bool> */
    public function capacidades(): array
    {
        return [
            'crearSegmento' => true,
            'crearPool' => true,
            'asignarPerfilPPP' => true,
            'crearBlackhole' => true,
            'crearListaFirewall' => true,
            'deprecarPrefijo' => true,
            'eliminarPorMarcador' => true,
        ];
    }

    public function crearSegmento(string $prefijo, string $interfaz, ?string $comentario = null): array
    {
        $comentario ??= self::MARCADOR_DEFECTO;

        return $this->ok([sprintf(
            '/ipv6 address add address=%s interface=%s comment="%s"',
            $prefijo,
            $interfaz,
            $this->escapar($comentario)
        )]);
    }

    public function crearPool(string $nombrePool, string $prefijo, int $longitudDelegacion): array
    {
        return $this->ok([sprintf(
            '/ipv6 pool add name=%s prefix=%s prefix-length=%d comment="%s"',
            $nombrePool,
            $prefijo,
            $longitudDelegacion,
            self::MARCADOR_DEFECTO
        )]);
    }

    public function asignarPerfilPPP(string $perfil, string $nombrePool): array
    {
        return $this->ok([sprintf(
            '/ppp profile set [find name=%s] dhcpv6-pd-pool=%s',
            $perfil,
            $nombrePool
        )]);
    }

    public function crearBlackhole(string $prefijo, ?string $comentario = null): array
    {
        $comentario ??= self::MARCADOR_DEFECTO;

        return $this->ok([sprintf(
            '/ipv6 route add dst-address=%s type=blackhole comment="%s"',
            $prefijo,
            $this->escapar($comentario)
        )]);
    }

    public function crearListaFirewall(string $lista, string $prefijo, ?string $comentario = null): array
    {
        $comentario ??= self::MARCADOR_DEFECTO;

        return $this->ok([sprintf(
            '/ipv6 firewall address-list add list=%s address=%s comment="%s"',
            $lista,
            $prefijo,
            $this->escapar($comentario)
        )]);
    }

    public function deprecarPrefijo(string $prefijo, ?string $comentario = null): array
    {
        $comentario ??= self::MARCADOR_DEFECTO . ' DEPRECADO';

        return $this->ok([
            sprintf('/ipv6 address disable [find address="%s"]', $prefijo),
            sprintf('/ipv6 address set [find address="%s"] comment="%s"', $prefijo, $this->escapar($comentario)),
        ]);
    }

    /**
     * Forma directa: válida en 6.x y 7.0-7.12 (el incidente de "this is configured
     * elsewhere" con entradas dinámicas es específico de 7.13+, ver override ahí).
     */
    public function eliminarPorMarcador(string $marcador): array
    {
        return $this->ok([sprintf(
            '/ipv6 address remove [find comment~"%s"]',
            $this->escapar($marcador)
        )]);
    }

    /** @param string[] $comandos */
    protected function ok(array $comandos): array
    {
        return ['comandos' => $comandos, 'advertencias' => []];
    }

    /** @param string $mensaje */
    protected function noSoportado(string $operacion, string $mensaje): array
    {
        return ['comandos' => [], 'advertencias' => ["[{$this->nombre()}] {$operacion} no soportado: {$mensaje}"]];
    }

    protected function escapar(string $texto): string
    {
        return str_replace('"', '\\"', $texto);
    }
}
