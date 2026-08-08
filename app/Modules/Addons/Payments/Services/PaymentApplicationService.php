<?php

namespace App\Modules\Addons\Payments\Services;

use App\Modules\Core\Clientes\Models\Client;
use App\Models\ClientInvoice;
use App\Models\MethodOfPayment;
use App\Models\Payment;
use App\Modules\Addons\Payments\Models\PaymentClabe;
use App\Modules\Addons\Payments\Models\PaymentReceipt;
use App\Modules\Addons\WhatsAppAgent\Services\EvolutionApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/**
 * Coordina los efectos secundarios cuando llega un pago SPEI:
 *   - registra fila en payments (polimórfica a Invoice o Client) — paymentable=
 *     Client dispara PaymentObserver→PaymentClientJob→ClientBillingService::
 *     billing(), ÚNICA ruta de reactivación de servicio (roadmap #160: la ruta
 *     secundaria activateClientService() se eliminó por reactivar sin checar
 *     balance)
 *   - notifica al cliente vía WhatsApp
 *   - guarda comprobante del webhook como JSON en payment_receipts
 *
 * No conoce de OpenPay directamente — recibe datos ya extraídos del
 * payload. Eso permite reusar el servicio para Stripe, Conekta, etc.
 */
class PaymentApplicationService
{
    public function __construct(
        private ?EvolutionApiService $whatsapp = null
    ) {
        // WhatsApp es opcional — si el módulo no está activo o no hay instancia
        // configurada, la notificación se loguea como warning y el pago igual procede.
    }

    /**
     * Busca el cliente dueño de la CLABE (única en payment_clabes).
     */
    public function findClientByClabe(string $clabe): ?Client
    {
        $row = PaymentClabe::where('clabe', $clabe)->where('is_active', true)->first();
        return $row?->client;
    }

    /**
     * Crea el registro en payments. Resuelve polimórfica (Invoice vs Client).
     *
     * Esperado en $data:
     *   client_id (int)
     *   amount (float)
     *   external_id (string) — transaction id del proveedor
     *   provider (string)    — 'openpay' | 'stripe' | ...
     *   comment (string opcional)
     */
    public function applyPayment(array $data): Payment
    {
        $client = Client::findOrFail($data['client_id']);
        // Overrides opcionales (retrocompatibles): la captura de mostrador pasa su
        // propio método/usuario/fecha; el webhook SPEI no los pasa y usa los defaults.
        $methodId = $data['method_id'] ?? $this->resolveMethodId();
        $userId   = $data['add_by'] ?? $this->resolveSystemUserId();

        // FIX (opción A, roadmap #191): el pago se registra SIEMPRE al Cliente
        // (path de mostrador puro). paymentable=Client dispara el observer
        // PaymentObserver→PaymentClientJob → abona balance + deja transacción de
        // crédito + billing + avance de corte. Los 3 callers (conciliación, SPEI,
        // captura-pago) quedan alineados a este flujo.
        //
        // matchPendingInvoice ya fue REDISEÑADO (roadmap #193, algoritmo híbrido sobre
        // deuda VIVA) pero sigue DESCONECTADO del default a propósito (ver comentario
        // del método): reconectarlo a los 3 callers reales es una decisión de dinero en
        // vivo aparte, fuera del alcance de este item.
        $paymentableType = Client::class;
        $paymentableId   = $client->id;
        $invoice         = null;

        // NO envolvemos en DB::transaction: con QUEUE_CONNECTION=sync, los
        // observers de Payment (PaymentObserver→PaymentClientJob) corren inline
        // tras el INSERT. Si el job downstream truena, una transaction outer
        // haría rollback del payment y perderíamos el registro del pago real.
        // El INSERT del payment sobrevive aunque el observer downstream falle.
        $attrs = [
            'number'                     => $data['external_id'] ?? null,
            'payment_method_id'          => $methodId,
            'date'                       => $data['date'] ?? now()->toDateString(),
            'amount'                     => $data['amount'],
            'payment_period'             => null,
            'comment'                    => $data['comment'] ?? "Pago {$data['provider']} (tx: {$data['external_id']})",
            'receipt'                    => '', // ver tabla payment_receipts
            'send_receipt_after_payment' => 0,
            'add_by'                     => $userId,
            'paymentable_id'             => $paymentableId,
            'paymentable_type'           => $paymentableType,
            'is_first_payment'           => 0,
            'enabled_payment_promise'    => 0,
        ];
        try {
            $payment = Payment::create($attrs);
        } catch (\Throwable $e) {
            // El observer/job post-INSERT tronó (cliente sin balance, etc.).
            // El INSERT YA ocurrió; rehidratamos por external_id y seguimos.
            Log::warning('SPEI: observer post-payment failed, recuperando row', [
                'error'       => $e->getMessage(),
                'external_id' => $data['external_id'],
            ]);
            $payment = Payment::where('number', $data['external_id'])
                ->where('paymentable_type', $paymentableType)
                ->latest('id')
                ->first();
            if (!$payment) {
                throw $e; // ni siquiera se persistió, propagar
            }
        }

        if ($invoice) {
            // Preservar sufijo del estado: "Pagar (del saldo...)" → "Pagado (del saldo...)"
            $nuevoEstado = preg_replace('/^Pagar\b/', 'Pagado', (string) $invoice->estado, 1);
            $invoice->update([
                'estado'       => $nuevoEstado ?: 'Pagado',
                'payment_date' => now()->toDateString(),
            ]);
            event(new \App\Events\InvoicePaid($invoice));
            Log::info('SPEI: factura aplicada', [
                'client_id'  => $client->id,
                'invoice_id' => $invoice->id,
                'amount'     => $data['amount'],
            ]);
        }

        return $payment;
    }

    /**
     * Estados de factura que representan deuda VIVA (roadmap #193). Excluye a propósito
     * el pool terminal "Pagar (del saldo de la cuenta)" (ya cubierto por balance, no es
     * deuda pendiente real) — ese era justo el bug del matcher anterior. Medido contra
     * datos reales de client_invoices (2026-08-04): Atrasado=8,292 / impagado=16,151 /
     * Partially paid=22.
     */
    private const ESTADOS_DEUDA_VIVA = ['Atrasado', 'impagado', 'Partially paid'];

    /**
     * Busca factura pendiente que cuadre con el monto. Devuelve [type, id, invoice?]
     * para el polimórfico de payments.
     *
     * ⚠️ DESCONECTADO del default de applyPayment (roadmap #191/#193) — sigue SIN
     * llamadores; NO invocar desde los 3 flujos (conciliación / SPEI / captura-pago)
     * sin decisión aparte de Irving para reconectarlo (eso es dinero en vivo, fuera
     * de alcance de este item).
     *
     * Algoritmo HÍBRIDO (Opción C, elegida por Irving en la Torre 2026-08-04) sobre
     * deuda VIVA (self::ESTADOS_DEUDA_VIVA): si hay EXACTAMENTE una factura viva con
     * el monto exacto, se usa esa (respeta la intención del pagador); si hay 0 o 2+
     * (ambiguo — medido: 1,698 combinaciones cliente+monto con 2+ facturas vivas del
     * mismo total), cae a FIFO sobre deuda viva (la más antigua no cubierta).
     *
     * El bug original: matcheaba 'Pagar%' (incluye el estado TERMINAL 'Pagar (del
     * saldo de la cuenta)', ~40k facturas ya cubiertas por saldo, NO deuda viva). Al
     * matchear, escribía paymentable=ClientInvoice → el PaymentObserver NO dispara
     * PaymentClientJob (solo mapea paymentable=Client) → el pago no abonaba balance
     * ni dejaba transacción: quedaba desregistrado (caso 7500).
     */
    private function matchPendingInvoice(Client $client, float $amount): array
    {
        $vivas = ClientInvoice::query()
            ->where('client_id', $client->id)
            ->whereIn('estado', self::ESTADOS_DEUDA_VIVA)
            ->where('total', $amount)
            ->orderBy('id', 'asc')
            ->get();

        $invoice = $vivas->count() === 1
            ? $vivas->first()
            : ClientInvoice::query()
                ->where('client_id', $client->id)
                ->whereIn('estado', self::ESTADOS_DEUDA_VIVA)
                ->orderBy('id', 'asc') // FIFO: más antigua no cubierta primero
                ->first();

        if ($invoice) {
            return [ClientInvoice::class, $invoice->id, $invoice];
        }
        return [Client::class, $client->id, null];
    }

    /**
     * Notifica al cliente vía WhatsApp. Errores son no-bloqueantes — se loguean
     * pero no abortan el flujo del webhook (el pago YA quedó registrado).
     */
    public function notifyClient(Client $client, Payment $payment): void
    {
        if (!$this->whatsapp) {
            $this->whatsapp = app(EvolutionApiService::class);
        }

        // TODO: la BD megaisp guarda el teléfono del cliente en client_main_information
        // (campo phone o similar). Por ahora usamos un placeholder y dejamos al operador
        // confirmar el campo exacto. Cuando se confirme, reemplazar este lookup.
        $phone = $this->resolveClientPhone($client);
        if (!$phone) {
            Log::warning('SPEI: cliente sin teléfono — WhatsApp no enviado', ['client_id' => $client->id]);
            return;
        }

        $body = sprintf(
            "¡Pago recibido! ✅\n\nMonto: $%.2f MXN\nReferencia: %s\nFecha: %s\n\nGracias por tu pago. Tu servicio queda activo en los próximos minutos.",
            $payment->amount,
            $payment->number ?? '—',
            $payment->date
        );

        try {
            $this->whatsapp->sendAndLog($phone, $body, null, [
                'source'     => 'payments.spei.webhook',
                'payment_id' => $payment->id,
                'client_id'  => $client->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('SPEI: WhatsApp sendAndLog falló', [
                'error'      => $e->getMessage(),
                'client_id'  => $client->id,
                'payment_id' => $payment->id,
            ]);
        }
    }

    /**
     * Guarda el payload del webhook como comprobante adjunto al pago.
     * El JSON crudo queda en disco para auditoría/reprocesamiento.
     */
    public function saveReceipt(Payment $payment, array $webhookData): PaymentReceipt
    {
        $filename = sprintf('payments/spei/%d_%s.json', $payment->id, now()->format('YmdHis'));
        $contents = json_encode($webhookData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        Storage::disk('local')->put($filename, $contents);

        return PaymentReceipt::create([
            'payment_id'    => $payment->id,
            'type'          => 'webhook',
            'file_path'     => $filename,
            'original_name' => "webhook_{$payment->id}.json",
            'size'          => strlen($contents),
            'metadata'      => [
                'provider'    => $webhookData['provider'] ?? 'openpay',
                'event_type'  => $webhookData['type'] ?? null,
                'external_id' => $webhookData['transaction']['id'] ?? null,
            ],
        ]);
    }

    /* ============================================================
     |  Internos
     * ============================================================ */

    private function resolveMethodId(): int
    {
        $id = MethodOfPayment::where('type', 'SPEI OpenPay')->value('id');
        if (!$id) {
            // La migración de seed debió crear esta fila. Si no existe es bug
            // de instalación — fallback a la primera disponible para no romper
            // el webhook (mejor pago con método incorrecto que pago perdido).
            $id = MethodOfPayment::orderBy('id')->value('id');
            Log::warning('SPEI: method_of_payments SPEI OpenPay no encontrado, usando fallback', ['fallback_id' => $id]);
        }
        return (int) $id;
    }

    /**
     * El webhook no tiene usuario autenticado pero payments.add_by es NOT NULL.
     * Resolvemos al primer SUPER_ADMIN como "usuario sistema". Resultado se
     * cachea en memoria del request para evitar 1 query por pago.
     */
    private ?int $cachedSystemUserId = null;
    private function resolveSystemUserId(): int
    {
        if ($this->cachedSystemUserId !== null) {
            return $this->cachedSystemUserId;
        }
        $role = Role::where('name', 'SUPER_ADMIN')->first();
        $userId = $role?->users()->orderBy('id')->value('id');
        return $this->cachedSystemUserId = (int) ($userId ?: 1);
    }

    private function resolveClientPhone(Client $client): ?string
    {
        // megaisp tiene el teléfono en client_main_information.phone — relación
        // hasOne. Si no carga, dejamos null y el caller hace logging.
        try {
            $info = $client->client_main_information ?? null;
            $phone = $info?->phone ?? null;
            if (!$phone) return null;
            // Normaliza: solo dígitos, prefijo país MX si no lo trae
            $digits = preg_replace('/\D+/', '', (string) $phone);
            if (strlen($digits) === 10) $digits = '52' . $digits;
            return $digits;
        } catch (\Throwable $e) {
            Log::warning('SPEI: error resolviendo teléfono', ['error' => $e->getMessage(), 'client_id' => $client->id]);
            return null;
        }
    }
}
