<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Contracts;

use Closure;

/**
 * Registro de fuentes de datos vivos del sistema.
 *
 * `SistemaResolver` y `GraficaResolver` no saben leer de ningún módulo: piden
 * aquí la fuente que el concepto declara en `config.fuente` y la ejecutan. Cada
 * fase posterior REGISTRA sus fuentes; ninguna toca los resolvedores.
 *
 * Contrato de una fuente: `fn (array $config, int $empresaId): array` y devuelve
 *   ['datos' => array, 'metricas' => array, 'mensaje' => ?string]
 *
 * En la Fase 0 el registro está VACÍO a propósito: los conceptos de tipo
 * `sistema`/`grafica` caen a `PendienteResolver` y muestran "sin fuente
 * configurada", que es la verdad. La Fase 1 los llena.
 */
class FuenteRegistry
{
    /** @var array<string, Closure> */
    private array $fuentes = [];

    public function registrar(string $clave, Closure $resolver): void
    {
        $this->fuentes[$clave] = $resolver;
    }

    public function tiene(?string $clave): bool
    {
        return $clave !== null && isset($this->fuentes[$clave]);
    }

    /** @return array{datos: array, metricas: array, mensaje: ?string} */
    public function ejecutar(string $clave, array $config, int $empresaId): array
    {
        if (! isset($this->fuentes[$clave])) {
            throw new \InvalidArgumentException("Fuente no registrada: '{$clave}'.");
        }

        $salida = ($this->fuentes[$clave])($config, $empresaId);

        return [
            'datos'    => $salida['datos'] ?? [],
            'metricas' => $salida['metricas'] ?? [],
            'mensaje'  => $salida['mensaje'] ?? null,
        ];
    }

    /** @return string[] */
    public function claves(): array
    {
        return array_keys($this->fuentes);
    }
}
