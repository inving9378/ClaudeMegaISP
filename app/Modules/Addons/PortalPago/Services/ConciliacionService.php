<?php

namespace App\Modules\Addons\PortalPago\Services;

use App\Events\InvoicePaid;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Addons\PortalPago\Models\PortalPagoPaymentLink;
use App\Modules\Addons\PortalPago\Models\PortalPagoPaymentReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Conciliación efectiva de un pago validado por CEP.
 *
 * REUTILIZA el patrón probado de PortalCliente\PortalPagoController::registrarPago
 * (detectado en Paso 0), con las correcciones de esquema verificadas:
 *
 *   - El Payment es polimórfico a App\Models\Client (NO a ClientInvoice). Solo
 *     así PaymentObserver despacha PaymentClientJob → ClientBillingService →
 *     desbloqueo MikroTik + activarCliente(). Esa es la ÚNICA ruta de
 *     reactivación; NO se llama activateClientService() ni se tocan
 *     client_internet_services directamente.
 *   - La tabla payments NO tiene columnas client_id ni payment_method (string);
 *     se usa payment_method_id + paymentable_*.
 *   - InvoicePaid recibe UN solo argumento ($invoice).
 *
 * Todo el bloque va dentro de DB::transaction(): si algo falla, no queda un
 * pago a medias. El reporte se deja en pendiente_validacion para que el
 * operador lo apruebe a mano desde la bandeja.
 */
class ConciliacionService
{
    /**
     * @throws \Throwable si la transacción falla (la maneja el llamador).
     */
    public function conciliar(PortalPagoPaymentReport $report): Payment
    {
        $link    = $report->paymentLink()->firstOrFail();
        $invoice = ClientInvoice::findOrFail($link->document_id);

        return DB::transaction(function () use ($link, $invoice, $report) {
            // 1. Registrar el pago (polimórfico a Client → dispara la cadena observer).
            $payment = Payment::create([
                'number'                  => $report->clave_rastreo,
                'payment_method_id'       => (int) config('pagos.payment_method_id', 2),
                'date'                    => now()->toDateTimeString(),
                'amount'                  => $link->monto_esperado,
                'receipt'                 => $report->clave_rastreo, // clave de rastreo = comprobante externo
                'comment'                 => 'Conciliado por CEP Banxico — Portal de Pago',
                'add_by'                  => User::systemBot()?->id ?? 0, // MEGAISP; sin FK en add_by, 0 como fallback si el bot no existe
                'paymentable_id'          => $link->client_id,
                'paymentable_type'        => Client::class,
                'is_first_payment'        => 0,
                'enabled_payment_promise' => 0,
            ]);

            // 2. Marcar la factura legacy con formato DD/MM/YYYY y anexar el id al CSV.
            $nuevoEstado = preg_replace('/^Pagar\b/', 'Pagado', (string) $invoice->estado, 1) ?: 'Pagado';
            $invoice->update([
                'estado'       => $nuevoEstado,
                'payment_date' => now()->format('d/m/Y'),
                'payment'      => $invoice->payment
                    ? $invoice->payment . ',' . $payment->id
                    : (string) $payment->id,
            ]);

            // 3. Disparar InvoicePaid (comisiones de Embajadores). UN solo argumento.
            event(new InvoicePaid($invoice));

            // 4. Cerrar la liga como conciliada.
            $link->update(['estado' => PortalPagoPaymentLink::ESTADO_CONCILIADO]);

            Log::info('PortalPago: conciliación aplicada', [
                'report_id'  => $report->id,
                'link_id'    => $link->id,
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'client_id'  => $link->client_id,
                'amount'     => (string) $link->monto_esperado,
            ]);

            return $payment;
        });
    }
}
