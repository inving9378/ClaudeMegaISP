<?php

namespace App\Services\Finance\Timbrado;

use App\Models\ClientFiscalData;
use App\Models\Payment;

/**
 * Contrato para el servicio de timbrado CFDI 4.0.
 *
 * Implementaciones futuras: Facturama, Finkok, SW Sapien, Formas Digitales.
 * Mientras no haya PAC configurado, se usa NullTimbradoService que lanza
 * PacNoConfiguradoException en todos los métodos.
 */
interface TimbradoServiceInterface
{
    /**
     * Emite una factura CFDI 4.0 para el pago dado.
     * Devuelve un array con: uuid, xml_path, pdf_path, timbrado_at, folio_fiscal.
     *
     * @throws PacNoConfiguradoException si no hay PAC configurado.
     * @throws \RuntimeException         en errores de timbrado.
     */
    public function emitirFactura(Payment $payment, ClientFiscalData $fiscalData): array;

    /**
     * Cancela una factura ya timbrada por su UUID.
     *
     * @throws PacNoConfiguradoException si no hay PAC configurado.
     */
    public function cancelarFactura(string $uuid, string $motivo = '02'): bool;

    /**
     * Retorna true si el PAC está configurado y disponible.
     */
    public function isDisponible(): bool;

    /**
     * Nombre del proveedor PAC activo (para UI).
     */
    public function nombreProveedor(): string;
}
