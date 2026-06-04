<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\Finance\Billing\BillingDocumentService;
use Illuminate\Support\Facades\Log;

/**
 * Genera Ticket + Recibo al confirmar un pago (evento created).
 * Idempotente: BillingDocumentService verifica (payment_id, document_type)
 * antes de insertar. No toca el canal de envío legacy (*Email models).
 */
class PaymentBillingObserver
{
    public function created(Payment $payment): void
    {
        // Aplica SOLO a pagos de clientes (no a otros paymentable_type futuros)
        if ($payment->paymentable_type !== 'App\Models\Client') {
            return;
        }

        try {
            (new BillingDocumentService())->generarTicketYRecibo($payment);
        } catch (\Throwable $e) {
            // Un fallo en la generación de documentos NUNCA debe bloquear el pago
            Log::error("[PaymentBillingObserver] payment#{$payment->id}: " . $e->getMessage());
        }
    }
}
