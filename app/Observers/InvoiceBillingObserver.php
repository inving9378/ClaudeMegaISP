<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\Finance\Billing\DocumentoNotificacionResolver;
use Illuminate\Support\Facades\Log;

/**
 * FAC-04 (item #509): cuando una factura pasa a PAGADA se cancelan las notificaciones de su
 * prefactura que siguen en espera — ya no tiene sentido cobrar algo pagado. La factura pagada
 * sale en el lugar del recibo del pago (DocumentoNotificacionResolver, al enviar).
 *
 * Es el único punto común: el cajero, la conciliación, SPEI/OpenPay, el espejo de
 * client_invoices y «marcar como pagada» terminan guardando el modelo Invoice. Corre DESPUÉS del
 * commit ($afterCommit): si la transacción del pago se revierte, no se cancela nada. Nunca
 * bloquea el pago (errores solo al log). Pagos parciales no tocan nada.
 */
class InvoiceBillingObserver
{
    public bool $afterCommit = true;

    public function updated(Invoice $invoice): void
    {
        if (! $invoice->wasChanged('status') || $invoice->status !== Invoice::STATUS_PAID) {
            return;
        }

        try {
            $n = app(DocumentoNotificacionResolver::class)->cancelarPrefacturasPendientes($invoice);
            if ($n > 0) {
                Log::info("[InvoiceBillingObserver] factura #{$invoice->id} pagada: {$n} prefactura(s) en espera cancelada(s).");
            }
        } catch (\Throwable $e) {
            Log::error("[InvoiceBillingObserver] factura #{$invoice->id}: " . $e->getMessage());
        }
    }
}
