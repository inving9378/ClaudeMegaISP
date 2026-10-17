<?php

namespace App\Observers\Facturacion;

use App\Services\Clientes\HistorialCliente;

/**
 * FR-04 (#538): registra en client_historial_cambios cada cambio de estado y de contrato hecho por
 * Eloquent (ficha, bloqueo automático, activación, inactivación, promesas…). Se registra en las DOS
 * clases de ClientMainInformation; las actualizaciones por query builder se registran donde ocurren.
 */
class HistorialClienteObserver
{
    public function updated($info): void
    {
        foreach (HistorialCliente::CAMPOS as $columna => $campo) {
            if ($info->wasChanged($columna)) {
                HistorialCliente::registrar((int) $info->client_id, $campo, $info->getOriginal($columna), $info->{$columna});
            }
        }
    }
}
