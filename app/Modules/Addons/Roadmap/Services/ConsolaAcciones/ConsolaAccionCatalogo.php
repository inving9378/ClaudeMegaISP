<?php

namespace App\Modules\Addons\Roadmap\Services\ConsolaAcciones;

/**
 * Item #876 — registro en memoria de las acciones declaradas. Un solo singleton por request
 * (ver `ModuleServiceProvider::register()`), poblado UNA vez por `TorreAccionesCatalogo`.
 */
class ConsolaAccionCatalogo
{
    /** @var array<string, ConsolaAccion> */
    private array $acciones = [];

    /** Declara una acción. Lanza si la clave ya existe (evita pisar una declaración previa). */
    public function declarar(array $definicion): ConsolaAccion
    {
        $accion = new ConsolaAccion($definicion);

        if (isset($this->acciones[$accion->clave])) {
            throw new \InvalidArgumentException("ConsolaAccionCatalogo: la clave '{$accion->clave}' ya está declarada.");
        }

        $this->acciones[$accion->clave] = $accion;

        return $accion;
    }

    public function obtener(string $clave): ?ConsolaAccion
    {
        return $this->acciones[$clave] ?? null;
    }

    /** @return ConsolaAccion[] */
    public function todas(): array
    {
        return array_values($this->acciones);
    }
}
