<?php

namespace App\Services\Finance\Invoice;

use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

/**
 * Dual-write de la Fase 2 (roadmap #632/#721): cuando un flujo de cobro marca
 * client_invoices.estado='Pagado', además refleja el estado en la fila
 * espejo de `invoices` (la crea si no existe). client_invoices sigue siendo
 * la fuente de verdad; esto es solo un espejo detrás de un feature flag.
 *
 * Best-effort a propósito: un fallo aquí NUNCA debe tumbar ni revertir el
 * cobro real (jamás se relanza la excepción — se loguea y se sigue).
 */
class InvoiceMirrorService
{
    public function mirrorPaid(int $clientInvoiceId, int $clientId, float $total, ?int $paymentId = null): ?Invoice
    {
        if (!config('billing.dual_write_invoices')) {
            return null;
        }

        try {
            $today = now()->toDateString();

            $invoice = Invoice::withTrashed()
                ->where('client_invoice_id', $clientInvoiceId)
                ->first();

            if ($invoice) {
                $invoice->update([
                    'status'          => Invoice::STATUS_PAID,
                    'payment_date'    => $today,
                    'pending_balance' => 0,
                    'payment_id'      => $paymentId ?: $invoice->payment_id,
                ]);
                return $invoice;
            }

            return Invoice::create([
                'number'            => 'CI-' . $clientInvoiceId,
                'client_id'         => $clientId,
                'client_invoice_id' => $clientInvoiceId,
                'payment_id'        => $paymentId,
                'due_date'          => $today,
                'payment_date'      => $today,
                'subtotal'          => $total,
                'tax'               => 0,
                'total'             => $total,
                'pending_balance'   => 0,
                'status'            => Invoice::STATUS_PAID,
                'type'              => Invoice::TYPE_PAYMENT,
                'period'            => now()->format('Y-m'),
                'notes'             => "Espejo dual-write de client_invoices#{$clientInvoiceId} (Fase 2 #721)",
            ]);
        } catch (\Throwable $e) {
            Log::warning('billing.dual_write_invoices: fallo al espejar client_invoice, cobro real no afectado', [
                'client_invoice_id' => $clientInvoiceId,
                'client_id'         => $clientId,
                'payment_id'        => $paymentId,
                'error'             => $e->getMessage(),
            ]);
            return null;
        }
    }
}
