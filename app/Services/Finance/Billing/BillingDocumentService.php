<?php

namespace App\Services\Finance\Billing;

use App\Models\BillingConfig;
use App\Models\BillingNotification;
use App\Models\Invoice;
use App\Models\Payment;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Configuracion\Models\CompanyInformation;
use App\Modules\Core\Configuracion\Repositories\CompanyInformationRepository;
use App\Services\Finance\Invoice\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BillingDocumentService
{
    private CompanyInformationRepository $companyRepo;
    private InvoiceService $invoiceService;

    public function __construct()
    {
        $this->companyRepo  = new CompanyInformationRepository();
        $this->invoiceService = new InvoiceService();
    }

    // ─── API pública ──────────────────────────────────────────────────────────

    /**
     * Genera el PDF de prefactura y crea un BillingNotification en_espera.
     * Idempotente por (invoice_id, 'prefactura').
     * Respeta billing_config.send_prefactura_email.
     */
    public function generarPrefactura(Invoice $invoice): ?BillingNotification
    {
        $existing = BillingNotification::where('invoice_id', $invoice->id)
            ->where('document_type', BillingNotification::TYPE_PREFACTURA)
            ->whereIn('status', [BillingNotification::STATUS_WAITING, BillingNotification::STATUS_SENT])
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            $config  = BillingConfig::current();
            $client  = $invoice->client()->with('client_main_information')->first();
            $company = $this->getCompany();

            $pdfPath = $this->renderPdf('billing.prefactura', [
                'invoice'     => $invoice->load('items'),
                'client'      => $client,
                'company'     => $company,
                'periodLabel' => $this->invoiceService->getStrPeriodByMonthAndYear($invoice->period ?? now()->format('Y-m')),
            ], "prefactura_{$invoice->id}_{$invoice->number}.pdf");

            $sendByEmail = (bool) $config->send_prefactura_email;
            $window      = BillingNotification::makeWindow($config->windowHours());

            return BillingNotification::create([
                'client_id'            => $client->id,
                'document_type'        => BillingNotification::TYPE_PREFACTURA,
                'invoice_id'           => $invoice->id,
                'payment_id'           => null,
                'pdf_path'             => $pdfPath,
                'email'                => $client->client_main_information->email ?? null,
                'subject'              => "Tu prefactura del período " . $this->invoiceService->getStrPeriodByMonthAndYear($invoice->period ?? now()->format('Y-m')),
                'status'               => BillingNotification::STATUS_WAITING,
                'window_hours'         => $window,
                'notificar_despues_de' => $sendByEmail ? Carbon::now()->addHours($window) : null,
                'send_by_email'        => $sendByEmail,
            ]);
        } catch (\Throwable $e) {
            Log::error("[BillingDocumentService] Error generando prefactura invoice#{$invoice->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Genera Ticket + Recibo para un pago confirmado.
     * Idempotente por (payment_id, document_type).
     * El Ticket nunca se envía por correo salvo billing_config.send_ticket_email.
     */
    public function generarTicketYRecibo(Payment $payment): void
    {
        $config  = BillingConfig::current();
        $client  = $payment->client()->with('client_main_information')->first();
        $company = $this->getCompany();
        $method  = $payment->payment_method?->type ?? 'Efectivo';
        $folio   = 'REC-' . str_pad($payment->id, 8, '0', STR_PAD_LEFT);
        $periodo = $payment->getPaymentPeriodByInvoice() ?? ($payment->payment_period ?? '—');

        $items = $this->buildItemsFromPayment($payment);
        $subtotal = round(collect($items)->sum('subtotal'), 2);
        $tax      = round(collect($items)->sum('tax'), 2);
        $total    = (float) $payment->amount;

        // ── Ticket ──
        $this->upsertDocumento(
            payment: $payment,
            client: $client,
            type: BillingNotification::TYPE_TICKET,
            pdfView: 'billing.ticket',
            viewData: [
                'folio'      => 'TKT-' . str_pad($payment->id, 8, '0', STR_PAD_LEFT),
                'fecha'      => Carbon::parse($payment->date)->format('d/m/Y'),
                'hora'       => Carbon::parse($payment->date)->format('H:i'),
                'cajero'     => optional(\App\Models\User::find($payment->add_by))->name ?? 'Sistema',
                'client'     => $client,
                'company'    => $company,
                'items'      => $items,
                'subtotal'   => $subtotal,
                'tax'        => $tax,
                'total'      => $total,
                'metodoPago' => $method,
                'referencia' => $payment->receipt ?? null,
                'payment'    => $payment,
            ],
            fileName: "ticket_{$payment->id}.pdf",
            sendEmail: (bool) $config->send_ticket_email,
            subject: null,
            window: BillingNotification::makeWindow($config->windowHours()),
        );

        // ── Recibo ──
        $this->upsertDocumento(
            payment: $payment,
            client: $client,
            type: BillingNotification::TYPE_RECIBO,
            pdfView: 'billing.recibo',
            viewData: [
                'folio'       => $folio,
                'fechaPago'   => Carbon::parse($payment->date)->format('d/m/Y H:i'),
                'periodLabel' => $periodo,
                'client'      => $client,
                'company'     => $company,
                'items'       => $items,
                'subtotal'    => $subtotal,
                'tax'         => $tax,
                'total'       => $total,
                'metodoPago'  => $method,
                'referencia'  => $payment->receipt ?? null,
                'payment'     => $payment,
            ],
            fileName: "recibo_{$payment->id}.pdf",
            sendEmail: true,
            subject: "Recibo de pago #{$folio} — {$company->company_name}",
            window: BillingNotification::makeWindow($config->windowHours()),
        );
    }

    /**
     * Regenera el PDF de una notificación existente y reinicia su ventana.
     * Soporta prefactura (invoice_id) o recibo/ticket (payment_id).
     */
    public function regenerar(BillingNotification $notification): bool
    {
        try {
            match ($notification->document_type) {
                BillingNotification::TYPE_PREFACTURA => $this->regenerarPrefactura($notification),
                BillingNotification::TYPE_RECIBO, BillingNotification::TYPE_TICKET => $this->regenerarPagoDoc($notification),
            };
            return true;
        } catch (\Throwable $e) {
            Log::error("[BillingDocumentService] Error regenerando notif#{$notification->id}: " . $e->getMessage());
            return false;
        }
    }

    // ─── Privados ─────────────────────────────────────────────────────────────

    private function upsertDocumento(
        Payment $payment,
        Client $client,
        string $type,
        string $pdfView,
        array $viewData,
        string $fileName,
        bool $sendEmail,
        ?string $subject,
        int $window,
    ): void {
        $existing = BillingNotification::where('payment_id', $payment->id)
            ->where('document_type', $type)
            ->whereIn('status', [BillingNotification::STATUS_WAITING, BillingNotification::STATUS_SENT])
            ->first();

        if ($existing) {
            return;
        }

        $pdfPath = $this->renderPdf($pdfView, $viewData, $fileName);

        BillingNotification::create([
            'client_id'            => $client->id,
            'document_type'        => $type,
            'invoice_id'           => null,
            'payment_id'           => $payment->id,
            'pdf_path'             => $pdfPath,
            'email'                => $client->client_main_information->email ?? null,
            'subject'              => $subject,
            'status'               => BillingNotification::STATUS_WAITING,
            'window_hours'         => $window,
            'notificar_despues_de' => $sendEmail ? Carbon::now()->addHours($window) : null,
            'send_by_email'        => $sendEmail,
        ]);
    }

    private function regenerarPrefactura(BillingNotification $notif): void
    {
        $invoice = Invoice::with(['items', 'client.client_main_information'])->findOrFail($notif->invoice_id);
        $company = $this->getCompany();
        $pdfPath = $this->renderPdf('billing.prefactura', [
            'invoice'     => $invoice,
            'client'      => $invoice->client,
            'company'     => $company,
            'periodLabel' => $this->invoiceService->getStrPeriodByMonthAndYear($invoice->period ?? now()->format('Y-m')),
        ], "prefactura_{$invoice->id}_{$invoice->number}.pdf");

        $notif->resetWindow($pdfPath);
    }

    private function regenerarPagoDoc(BillingNotification $notif): void
    {
        $payment = Payment::with(['client.client_main_information', 'payment_method'])->findOrFail($notif->payment_id);
        $this->generarTicketYRecibo($payment);
    }

    private function buildItemsFromPayment(Payment $payment): array
    {
        $items = [];
        foreach ($payment->invoices()->with('items')->get() as $invoice) {
            foreach ($invoice->items as $item) {
                $items[] = [
                    'name'     => $item->name,
                    'subtotal' => (float) $item->subtotal,
                    'tax'      => (float) $item->tax_amount,
                    'total'    => (float) $item->total,
                ];
            }
        }
        if (empty($items)) {
            // Fallback: si no hay items, usamos el monto del pago
            $taxRate = 0.16;
            $subtotal = round($payment->amount / (1 + $taxRate), 2);
            $tax = round($payment->amount - $subtotal, 2);
            $items[] = [
                'name'     => 'Servicio de internet',
                'subtotal' => $subtotal,
                'tax'      => $tax,
                'total'    => (float) $payment->amount,
            ];
        }
        return $items;
    }

    private function renderPdf(string $view, array $data, string $fileName): string
    {
        $pdf  = Pdf::loadView($view, $data)->setPaper('letter', 'portrait');
        $dir  = config('billing.pdf_path_prefix', 'billing/pdf');
        $path = "{$dir}/{$fileName}";

        Storage::disk(config('billing.pdf_disk', 'local'))->put($path, $pdf->output());

        return $path;
    }

    private function getCompany(): CompanyInformation
    {
        return CompanyInformation::with(['state', 'colony', 'municipality'])->first();
    }
}
