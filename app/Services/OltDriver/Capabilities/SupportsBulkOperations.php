<?php

namespace App\Services\OltDriver\Capabilities;

/**
 * Driver soporta operaciones en lote para eficiencia en cambios masivos.
 * Drivers que no tengan endpoint bulk pueden implementar estas con un loop interno.
 */
interface SupportsBulkOperations
{
    /**
     * Aplica un perfil de velocidad a múltiples ONUs en una sola llamada.
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function bulkSetSpeedProfile(array $data): array;

    /**
     * Obtiene inventario + señales + estados de una OLT en una sola operación
     * (implementaciones pueden hacerlo en paralelo internamente).
     *
     * Shape: ['success'=>bool,
     *   'onus'     => ['success'=>bool, 'rows'=>array<onu>],
     *   'signals'  => ['success'=>bool, 'rows'=>array<signal>],
     *   'statuses' => ['success'=>bool, 'rows'=>array<status>],
     * ]
     */
    public function getOnusBulk(string $oltId): array;
}
