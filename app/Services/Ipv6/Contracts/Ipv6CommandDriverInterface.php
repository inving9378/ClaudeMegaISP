<?php

namespace App\Services\Ipv6\Contracts;

/**
 * Interfaz común que traduce operaciones abstractas del plan IPv6 (#950 fase 1.4)
 * a comandos concretos de RouterOS. Cada driver representa una familia de versión
 * y declara qué operaciones soporta vía capacidades(); si una operación no está
 * soportada en esa familia, el driver debe devolver `comandos: []` +
 * `advertencias: [...]` — NUNCA un comando que pueda ser inválido en esa versión.
 *
 * Todos los métodos son PUROS: reciben datos, devuelven texto. Ningún driver
 * abre conexión ni toca un router real (eso es responsabilidad de otra fase,
 * fuera de alcance de #950 — ver decisión de Irving: solo generar/mostrar).
 */
interface Ipv6CommandDriverInterface
{
    /** Nombre legible de la familia de versión que representa este driver. */
    public function nombre(): string;

    /** @return array<string, bool> mapa operación => soportada en esta familia */
    public function capacidades(): array;

    /** @return array{comandos: string[], advertencias: string[]} */
    public function crearSegmento(string $prefijo, string $interfaz, ?string $comentario = null): array;

    /** @return array{comandos: string[], advertencias: string[]} */
    public function crearPool(string $nombrePool, string $prefijo, int $longitudDelegacion): array;

    /** @return array{comandos: string[], advertencias: string[]} */
    public function asignarPerfilPPP(string $perfil, string $nombrePool): array;

    /** @return array{comandos: string[], advertencias: string[]} */
    public function crearBlackhole(string $prefijo, ?string $comentario = null): array;

    /** @return array{comandos: string[], advertencias: string[]} */
    public function crearListaFirewall(string $lista, string $prefijo, ?string $comentario = null): array;

    /** @return array{comandos: string[], advertencias: string[]} */
    public function deprecarPrefijo(string $prefijo, ?string $comentario = null): array;

    /** @return array{comandos: string[], advertencias: string[]} */
    public function eliminarPorMarcador(string $marcador): array;
}
