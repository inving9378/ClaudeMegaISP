<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Aislamiento multi-tenant por cliente — patrón ESTÁNDAR del proyecto,
 * extraído del scopeForClient() de Flotas.
 *
 * Uso: el modelo aplica `use BelongsToClientTenant;`. Si su columna tenant
 * no es `client_id`, la redefine:
 *     protected string $tenantColumn = 'embajador_id';
 *
 * SEMÁNTICA DE SEGURIDAD (fail-closed por defecto):
 *  - clientId concreto (X): solo registros con tenantColumn = X. Las filas con
 *    tenantColumn NULL (huérfanas) quedan SIEMPRE excluidas (igualdad nunca casa NULL).
 *  - clientId NULL (el resolver no resolvió un cliente):
 *      · $allowNullTenant = true  → ve los registros sin dueño (NULL). EXCLUSIVO de
 *        módulos internos Meganet (Flotas). Hay que activarlo explícitamente.
 *      · $allowNullTenant = false (DEFAULT) → FAIL-CLOSED: cero resultados. Un registro
 *        huérfano jamás es visible para un cliente.
 *
 * @see \App\Services\Tenant\CurrentClientResolver  para resolver el clientId actual.
 */
trait BelongsToClientTenant
{
    public function scopeForClient(Builder $query, ?int $clientId): Builder
    {
        $column = $this->tenantColumnName();

        if ($clientId !== null) {
            return $query->where($column, $clientId);
        }

        if ($this->allowsNullTenant()) {
            // Solo Flotas: NULL = registros internos Meganet (sin dueño).
            return $query->whereNull($column);
        }

        // Módulos de cliente: sin cliente resuelto no se expone NADA.
        return $query->whereRaw('1 = 0');
    }

    protected function tenantColumnName(): string
    {
        return property_exists($this, 'tenantColumn') ? $this->tenantColumn : 'client_id';
    }

    protected function allowsNullTenant(): bool
    {
        return property_exists($this, 'allowNullTenant') ? (bool) $this->allowNullTenant : false;
    }
}
