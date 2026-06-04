<?php

namespace App\Services\Finance\Timbrado;

use App\Models\ClientFiscalData;
use App\Models\Payment;

/**
 * Implementación nula del servicio de timbrado.
 * Se usa mientras no haya un PAC configurado.
 * Todos los métodos lanzan PacNoConfiguradoException excepto isDisponible().
 */
class NullTimbradoService implements TimbradoServiceInterface
{
    public function emitirFactura(Payment $payment, ClientFiscalData $fiscalData): array
    {
        throw new PacNoConfiguradoException();
    }

    public function cancelarFactura(string $uuid, string $motivo = '02'): bool
    {
        throw new PacNoConfiguradoException();
    }

    public function isDisponible(): bool
    {
        return false;
    }

    public function nombreProveedor(): string
    {
        return 'Sin PAC configurado';
    }
}
