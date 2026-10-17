<?php

namespace Tests\Feature\Facturacion;

use App\Models\BillingNotification;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\Billing\DocumentoNotificacionResolver;
use App\Services\Finance\Billing\PrefacturaDataProvider;
use App\Services\Finance\Billing\PrefacturaPdfService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesApplication;

/**
 * FAC-04 (item #509, padre #482): la factura PAGADA reemplaza al recibo y cancela la prefactura
 * pendiente. Corre contra la BD de pruebas (`*_test`, candado de CreatesApplication) DENTRO de una
 * transacción que se revierte: no migra, no trunca, no deja filas. Sin salida externa: la cola
 * está falsa (PaymentClientJob no corre: nada de MikroTik), el disco de PDFs es falso, el correo va
 * al transporte `array` y el PDF de la plantilla se simula (PrefacturaPdfServiceFalso).
 */
class FacturaPagadaReemplazaReciboTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const CLIENTE_MODULAR = 'App\\Modules\\Core\\Clientes\\Models\\Client';
    private const CLIENTE_LEGACY = 'App\\Models\\Client';

    private PrefacturaPdfServiceFalso $pdf;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake(config('billing.pdf_disk', 'local'));
        config(['mail.default' => 'array', 'mail.mailers.array' => ['transport' => 'array'], 'mail.interceptar.activo' => false]);
        Mail::purge('array');

        DB::table('billing_config')->update(['send_ticket_email' => 0, 'envio_pausado' => 1, 'notification_window_hours' => 12]);

        $this->pdf = new PrefacturaPdfServiceFalso();
        $this->app->instance(PrefacturaPdfService::class, $this->pdf);
    }

    // ─── Fixtures sintéticos (dentro de la transacción) ──────────────────────────

    private function cliente(): int
    {
        $usuario = (int) DB::table('users')->value('id');
        $id = DB::table('clients')->insertGetId(['created_by' => $usuario, 'updated_by' => $usuario, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_main_information')->insert([
            'client_id' => $id, 'name' => 'CLIENTE SINTÉTICO FAC-04', 'email' => "fac04-{$id}@example.test",
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function factura(int $cliente, string $periodo = '2026-11', float $total = 449.0): Invoice
    {
        return Invoice::create([
            'number' => 'FAC04-' . $cliente . '-' . $periodo, 'client_id' => $cliente, 'type' => 'proforma',
            'period' => $periodo, 'subtotal' => round($total / 1.16, 2), 'tax' => round($total - $total / 1.16, 2),
            'total' => $total, 'pending_balance' => $total, 'status' => Invoice::STATUS_ISSUED,
            'due_date' => now()->addDays(7)->toDateString(),
        ]);
    }

    private function pago(int $cliente, string $tipo, float $monto = 449.0): Payment
    {
        return Payment::create([
            'payment_method_id' => 2, 'date' => now()->format('Y-m-d H:i:s'), 'amount' => $monto,
            'receipt' => 'FAC04', 'add_by' => (int) DB::table('users')->value('id'),
            'paymentable_id' => $cliente, 'paymentable_type' => $tipo,
        ]);
    }

    private function notificacionPrefactura(Invoice $invoice, string $status = BillingNotification::STATUS_WAITING): BillingNotification
    {
        return BillingNotification::create([
            'client_id' => $invoice->client_id, 'document_type' => BillingNotification::TYPE_PREFACTURA,
            'invoice_id' => $invoice->id, 'pdf_path' => 'billing/pdf/prefactura-vieja.pdf',
            'email' => "fac04-{$invoice->client_id}@example.test", 'subject' => 'Tu prefactura del período (Blade)',
            'status' => $status, 'window_hours' => 12, 'notificar_despues_de' => now()->subMinute(), 'send_by_email' => true,
        ]);
    }

    /** Lo que hacen InvoiceService/PaymentApplicationService al aplicar un pago a la factura. */
    private function aplicar(Invoice $invoice, Payment $payment, ?float $abono = null): void
    {
        $saldo = $abono === null ? 0.0 : max(0.0, (float) $invoice->pending_balance - $abono);
        $invoice->pending_balance = $saldo;
        $invoice->status = $saldo <= 0 ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIALLY_PAID;
        $invoice->payment_date = $saldo <= 0 ? now() : null;
        $invoice->payment_id = $payment->id;
        $invoice->payment_method = $payment->payment_method_id;
        $invoice->save();
    }

    private function recibo(Payment $payment): BillingNotification
    {
        return BillingNotification::where('payment_id', $payment->id)->where('document_type', BillingNotification::TYPE_RECIBO)->sole();
    }

    private function resolver(): DocumentoNotificacionResolver
    {
        return app(DocumentoNotificacionResolver::class);
    }

    // ─── Pago completo por cada canal ─────────────────────────────────────────────

    public static function canales(): array
    {
        return [
            'SPEI / conciliación WhatsApp (PaymentApplicationService)' => [self::CLIENTE_MODULAR],
            'OpenPay (webhook del portal)'                            => [self::CLIENTE_LEGACY],
            'Portal de Pago (ConciliacionService)'                    => [self::CLIENTE_LEGACY],
            'Cajero / manual (ClientTrait, PaymentService)'           => [self::CLIENTE_LEGACY],
        ];
    }

    /** @dataProvider canales */
    public function test_pago_completo_cancela_la_prefactura_y_manda_la_factura_en_lugar_del_recibo(string $tipo): void
    {
        $cliente = $this->cliente();
        $invoice = $this->factura($cliente);
        $prefactura = $this->notificacionPrefactura($invoice);

        $payment = $this->pago($cliente, $tipo);
        $recibo = $this->recibo($payment);   // también con el FQCN modular (antes no se generaba)

        $this->aplicar($invoice, $payment);

        $prefactura->refresh();
        $this->assertSame(BillingNotification::STATUS_CANCELLED, $prefactura->status);
        $this->assertSame(DocumentoNotificacionResolver::MOTIVO_PAGADA, $prefactura->error_log);

        $d = $this->resolver()->resolver($recibo);
        $this->assertSame(DocumentoNotificacionResolver::ENVIAR, $d['accion']);
        $this->assertSame('Tu factura ' . PrefacturaDataProvider::periodoLegible('2026-11') . ' (pagada) — Meganet', $d['documento']['asunto']);
        $this->assertCount(1, $d['documento']['adjuntos']);
        $this->assertStringStartsWith('Factura-', $d['documento']['adjuntos'][0]['name']);
        $this->assertSame([[$invoice->id, 'pagado']], $this->pdf->llamadas);
    }

    public function test_pago_parcial_no_cambia_documentos(): void
    {
        $cliente = $this->cliente();
        $invoice = $this->factura($cliente);
        $prefactura = $this->notificacionPrefactura($invoice);
        $payment = $this->pago($cliente, self::CLIENTE_LEGACY, 200.0);

        $this->aplicar($invoice, $payment, 200.0);

        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->fresh()->status);
        $this->assertSame(BillingNotification::STATUS_WAITING, $prefactura->fresh()->status);

        $recibo = $this->resolver()->resolver($this->recibo($payment));
        $this->assertSame(DocumentoNotificacionResolver::ENVIAR, $recibo['accion']);
        $this->assertNull($recibo['documento'], 'abono → el recibo de siempre');

        $pre = $this->resolver()->resolver($prefactura->fresh());
        $this->assertSame('Tu prefactura ' . PrefacturaDataProvider::periodoLegible('2026-11') . ' — Meganet', $pre['documento']['asunto']);
    }

    // ─── Alcance, idempotencia y transacción ──────────────────────────────────────

    public function test_no_toca_enviadas_ni_otras_facturas_y_es_idempotente(): void
    {
        $cliente = $this->cliente();
        $invoice = $this->factura($cliente);
        $otra = $this->factura($cliente, '2026-12');
        $enviada = $this->notificacionPrefactura($invoice, BillingNotification::STATUS_SENT);
        $deOtra = $this->notificacionPrefactura($otra);
        $payment = $this->pago($cliente, self::CLIENTE_LEGACY);

        $this->aplicar($invoice, $payment);
        $invoice->save();   // guardarla de nuevo sin cambio de estado no hace nada

        $this->assertSame(BillingNotification::STATUS_SENT, $enviada->fresh()->status);
        $this->assertSame(BillingNotification::STATUS_WAITING, $deOtra->fresh()->status);
        $this->assertSame(0, $this->resolver()->cancelarPrefacturasPendientes($invoice));
    }

    public function test_si_la_transaccion_del_pago_se_revierte_no_se_cancela_nada(): void
    {
        $cliente = $this->cliente();
        $invoice = $this->factura($cliente);
        $prefactura = $this->notificacionPrefactura($invoice);
        $payment = $this->pago($cliente, self::CLIENTE_LEGACY);

        try {
            DB::transaction(function () use ($invoice, $payment) {
                $this->aplicar($invoice, $payment);
                throw new \RuntimeException('falla después de marcar pagada');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(BillingNotification::STATUS_WAITING, $prefactura->fresh()->status);
        $this->assertNotSame(Invoice::STATUS_PAID, $invoice->fresh()->status);
    }

    // ─── Ticket ───────────────────────────────────────────────────────────────────

    public function test_ticket_sin_pdf_si_no_se_envia_y_con_pdf_si_se_envia(): void
    {
        $cliente = $this->cliente();

        $sin = $this->pago($cliente, self::CLIENTE_LEGACY);
        $ticket = BillingNotification::where('payment_id', $sin->id)->where('document_type', BillingNotification::TYPE_TICKET)->sole();
        $this->assertNull($ticket->pdf_path);
        $this->assertFalse($ticket->send_by_email);
        $this->assertNotNull($this->recibo($sin)->pdf_path, 'el recibo se sigue generando (lo descarga la app)');

        DB::table('billing_config')->update(['send_ticket_email' => 1]);
        $con = $this->pago($cliente, self::CLIENTE_LEGACY);
        $ticket2 = BillingNotification::where('payment_id', $con->id)->where('document_type', BillingNotification::TYPE_TICKET)->sole();
        $this->assertNotNull($ticket2->pdf_path);
        Storage::disk(config('billing.pdf_disk', 'local'))->assertExists($ticket2->pdf_path);
    }

    // ─── Revalidación al enviar (el comando) ──────────────────────────────────────

    public function test_al_enviar_revalida_cancela_la_prefactura_pagada_y_manda_la_factura(): void
    {
        DB::table('billing_config')->update(['envio_pausado' => 0]);
        $cliente = $this->cliente();
        $invoice = $this->factura($cliente);
        $prefactura = $this->notificacionPrefactura($invoice);
        $payment = $this->pago($cliente, self::CLIENTE_MODULAR);
        $recibo = $this->recibo($payment);
        $recibo->update(['notificar_despues_de' => now()->subMinute()]);

        // Carrera: la factura quedó pagada sin pasar por el observer (p. ej. antes de desplegar).
        DB::table('invoices')->where('id', $invoice->id)->update(['status' => Invoice::STATUS_PAID, 'pending_balance' => 0, 'payment_id' => $payment->id, 'payment_date' => now()]);

        $this->artisan('billing:send-pending-notifications', ['--limit' => 50])->assertExitCode(0);

        $this->assertSame(BillingNotification::STATUS_CANCELLED, $prefactura->fresh()->status);
        $this->assertSame(BillingNotification::STATUS_SENT, $recibo->fresh()->status);

        $enviados = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $enviados, 'solo la factura; la prefactura de algo pagado no sale');
        $mensaje = $enviados->first()->getOriginalMessage();
        $this->assertSame('Tu factura ' . PrefacturaDataProvider::periodoLegible('2026-11') . ' (pagada) — Meganet', $mensaje->getSubject());
        $nombres = array_map(fn ($a) => $a->getFilename(), $mensaje->getAttachments());
        $this->assertCount(1, $nombres);
        $this->assertStringStartsWith('Factura-', $nombres[0]);

        // Reintento: un segundo barrido no vuelve a mandar nada.
        $this->artisan('billing:send-pending-notifications', ['--limit' => 50])->assertExitCode(0);
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_factura_pagada_que_no_se_puede_generar_no_se_manda_ni_cae_al_recibo(): void
    {
        DB::table('billing_config')->update(['envio_pausado' => 0]);
        $cliente = $this->cliente();
        $invoice = $this->factura($cliente);
        $payment = $this->pago($cliente, self::CLIENTE_LEGACY);
        $recibo = $this->recibo($payment);
        $recibo->update(['notificar_despues_de' => now()->subMinute()]);
        $this->aplicar($invoice, $payment);
        $this->pdf->fallaPara = [$invoice->id];

        $this->artisan('billing:send-pending-notifications')->assertExitCode(0);

        $this->assertSame(BillingNotification::STATUS_ERROR, $recibo->fresh()->status);
        $this->assertStringContainsString('sin respaldo', $recibo->fresh()->error_log);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_con_la_pausa_puesta_no_sale_nada(): void
    {
        $cliente = $this->cliente();
        $invoice = $this->factura($cliente);
        $prefactura = $this->notificacionPrefactura($invoice);

        $this->artisan('billing:send-pending-notifications')->assertExitCode(0);

        $this->assertSame(BillingNotification::STATUS_WAITING, $prefactura->fresh()->status);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_dry_run_no_genera_pdfs_ni_cambia_estados(): void
    {
        $cliente = $this->cliente();
        $invoice = $this->factura($cliente);
        $prefactura = $this->notificacionPrefactura($invoice);

        $this->artisan('billing:send-pending-notifications', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame([], $this->pdf->llamadas);
        $this->assertSame(BillingNotification::STATUS_WAITING, $prefactura->fresh()->status);
    }
}
