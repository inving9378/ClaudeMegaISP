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
        // Aplica SOLO a pagos de clientes. Hay dos FQCN reales de Client: el legacy
        // App\Models\Client (cajero, OpenPay, Portal de Pago) y el modular
        // App\Modules\Core\Clientes\Models\Client (SPEI/conciliación vía
        // PaymentApplicationService). Antes solo se aceptaba el primero y esos pagos se
        // quedaban sin ticket ni recibo (FAC-04, item #509).
        if (! $payment->isModelClient()) {
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
