<?php

namespace Tests\Feature\Portal;

use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Repository\ClientRepository;
use App\Jobs\Client\Payment\PaymentClientJob;
use App\Modules\Addons\PortalCliente\Services\OpenpayService;
use App\Modules\Addons\PortalCliente\Services\OpenpayTransactionException;
use App\Models\Payment;
use App\Services\ClientService\ClientBillingService;
use Illuminate\Support\Facades\DB;

/**
 * Fase 6 — Tests de pago OpenPay (cargo síncrono + scope + idempotencia).
 *
 * Usa DatabaseTransactions → rollback automático, sin migrate:fresh.
 * Mockea OpenpayService para no hacer cargos reales en sandbox.
 */
class OpenPayTest extends PortalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    // ─────────────────────────────────────────────────────────────────
    // (a) Scope: no se puede pagar una factura ajena
    // ─────────────────────────────────────────────────────────────────

    public function test_no_puede_pagar_factura_ajena(): void
    {
        $clienteA = $this->crearClientePortal();
        $clienteB = $this->crearClientePortal();
        $facturaB = $this->crearFactura($clienteB->client_id, ['estado' => 'Atrasado']);

        // clienteA intenta pagar factura de clienteB
        $response = $this->actingAs($clienteA, 'cliente')
            ->postJson("/portal/facturas/{$facturaB}/pagar", [
                'token'             => 'tok_test_fake',
                'device_session_id' => 'sess_test_fake',
            ]);

        $response->assertStatus(404);
        $this->assertGuest('web');

        // Verificar que no se creó ningún intento de pago
        $intentos = DB::table('portal_payment_attempts')
            ->where('invoice_id', $facturaB)
            ->where('client_id', $clienteA->client_id)
            ->count();
        $this->assertEquals(0, $intentos);
    }

    // ─────────────────────────────────────────────────────────────────
    // (b) Idempotencia: doble submit no genera doble cargo
    // ─────────────────────────────────────────────────────────────────

    public function test_doble_submit_no_genera_doble_cargo(): void
    {
        $cliente = $this->crearClientePortal();
        $factura = $this->crearFactura($cliente->client_id, ['estado' => 'Pendiente']);

        // Simular que ya hay un intento `pending` activo para esta factura
        DB::table('portal_payment_attempts')->insert([
            'client_id'  => $cliente->client_id,
            'invoice_id' => $factura,
            'order_id'   => 'pp-test-idempotencia-' . uniqid(),
            'amount'     => 399.00,
            'status'     => 'pending',
            'created_at' => now(),       // dentro de los últimos 30s
            'updated_at' => now(),
        ]);

        // Mock: el servicio NO debe ser llamado si hay un pending activo
        $this->app->bind(OpenpayService::class, function () {
            $mock = $this->createMock(OpenpayService::class);
            $mock->expects($this->never())->method('cobrarTarjeta');
            return $mock;
        });

        $response = $this->actingAs($cliente, 'cliente')
            ->postJson("/portal/facturas/{$factura}/pagar", [
                'token'             => 'tok_test_fake',
                'device_session_id' => 'sess_test_fake',
            ]);

        $response->assertOk()
            ->assertJson(['success' => false])
            ->assertJsonFragment(['error' => 'Tu pago está siendo procesado. Espera un momento.']);

        // Solo 1 intento (el que insertamos manualmente), no un segundo
        $totalIntentos = DB::table('portal_payment_attempts')
            ->where('invoice_id', $factura)
            ->count();
        $this->assertEquals(1, $totalIntentos);
    }

    // ─────────────────────────────────────────────────────────────────
    // (c) Cargo completed → exactamente un pago + factura actualizada
    // ─────────────────────────────────────────────────────────────────

    public function test_cargo_completado_registra_un_pago_y_actualiza_factura(): void
    {
        $cliente = $this->crearClientePortal();
        // Balance requerido: PaymentClientJob lee balance->amount tras acreditar
        $this->crearBalance($cliente->client_id, -399.00);

        $factura = $this->crearFactura($cliente->client_id, [
            'estado' => 'Atrasado',
            'total'  => 399.00,
        ]);

        $chargeId = 'trk_sandbox_test_' . uniqid();

        // Mock de OpenpayService que devuelve un cargo completado
        $this->app->bind(OpenpayService::class, function () use ($chargeId) {
            $mock = $this->createMock(OpenpayService::class);
            $mock->expects($this->once())
                ->method('cobrarTarjeta')
                ->willReturn((object) [
                    'id'     => $chargeId,
                    'status' => 'completed',
                    'amount' => 399.00,
                ]);
            return $mock;
        });

        $response = $this->actingAs($cliente, 'cliente')
            ->postJson("/portal/facturas/{$factura}/pagar", [
                'token'             => 'tok_test_aprobado',
                'device_session_id' => 'sess_test_aprobado',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        // Exactamente 1 pago con el charge_id como receipt
        $pagos = DB::table('payments')
            ->where('receipt', $chargeId)
            ->get();
        $this->assertCount(1, $pagos, 'Debe haber exactamente un registro de pago');

        $pago = $pagos->first();
        $this->assertEquals(399.00, (float) $pago->amount);
        $this->assertEquals($cliente->client_id, $pago->paymentable_id);
        $this->assertEquals('App\\Models\\Client', $pago->paymentable_type);

        // La factura debe quedar como Pagado
        $facturaActualizada = DB::table('client_invoices')->where('id', $factura)->first();
        $this->assertEquals('Pagado', $facturaActualizada->estado);
        $this->assertNotNull($facturaActualizada->payment_date);
        $this->assertStringContainsString((string) $pago->id, $facturaActualizada->payment);

        // El intento debe quedar como completed
        $intento = DB::table('portal_payment_attempts')
            ->where('invoice_id', $factura)
            ->where('status', 'completed')
            ->first();
        $this->assertNotNull($intento, 'El intento debe estar marcado como completed');
        $this->assertEquals($chargeId, $intento->openpay_charge_id);
    }

    // ─────────────────────────────────────────────────────────────────
    // (d) Cargo failed → NO toca la factura, intento marcado como failed
    // ─────────────────────────────────────────────────────────────────

    public function test_cargo_fallido_no_toca_la_factura(): void
    {
        $cliente = $this->crearClientePortal();
        $factura = $this->crearFactura($cliente->client_id, [
            'estado' => 'Atrasado',
            'total'  => 399.00,
        ]);

        // Mock que lanza excepción de tarjeta declinada
        $this->app->bind(OpenpayService::class, function () {
            $mock = $this->createMock(OpenpayService::class);
            $mock->expects($this->once())
                ->method('cobrarTarjeta')
                ->willThrowException(new OpenpayTransactionException(
                    'Tu tarjeta fue declinada. Contacta a tu banco o intenta con otra tarjeta.',
                    3001
                ));
            return $mock;
        });

        $response = $this->actingAs($cliente, 'cliente')
            ->postJson("/portal/facturas/{$factura}/pagar", [
                'token'             => 'tok_test_declinado',
                'device_session_id' => 'sess_test_declinado',
            ]);

        $response->assertOk()->assertJson(['success' => false]);
        $this->assertStringContainsString('declinada', $response->json('error'));

        // La factura NO debe haber cambiado de estado
        $facturaActualizada = DB::table('client_invoices')->where('id', $factura)->first();
        $this->assertEquals('Atrasado', $facturaActualizada->estado);
        $this->assertNull($facturaActualizada->payment_date);

        // NO debe haber ningún pago registrado con error
        $pagosConError = DB::table('payments')
            ->where('paymentable_id', $cliente->client_id)
            ->where('comment', 'LIKE', '%OpenPay%')
            ->count();
        $this->assertEquals(0, $pagosConError, 'Un cargo fallido NO debe crear un registro en payments');

        // El intento debe estar como failed
        $intento = DB::table('portal_payment_attempts')
            ->where('invoice_id', $factura)
            ->where('status', 'failed')
            ->first();
        $this->assertNotNull($intento, 'El intento fallido debe quedar registrado como failed');
        $this->assertEquals(3001, $intento->error_code);
    }

    // ─────────────────────────────────────────────────────────────────
    // (e) No se puede pagar una factura que ya está pagada
    // ─────────────────────────────────────────────────────────────────

    public function test_no_puede_pagar_factura_ya_pagada(): void
    {
        $cliente = $this->crearClientePortal();
        $factura = $this->crearFactura($cliente->client_id, ['estado' => 'Pagado']);

        // El servicio NO debe ser invocado para facturas ya pagadas
        $this->app->bind(OpenpayService::class, function () {
            $mock = $this->createMock(OpenpayService::class);
            $mock->expects($this->never())->method('cobrarTarjeta');
            return $mock;
        });

        $response = $this->actingAs($cliente, 'cliente')
            ->postJson("/portal/facturas/{$factura}/pagar", [
                'token'             => 'tok_test_fake',
                'device_session_id' => 'sess_fake',
            ]);

        $response->assertOk()->assertJson(['success' => false]);
        $this->assertStringContainsString('ya fue pagada', $response->json('error'));
    }

    // ─────────────────────────────────────────────────────────────────
    // (e2) Cargo completed vía Eloquent → balance acreditado + transacción crédito
    //      Verifica la reconciliación completa con el panel admin.
    //      Con QUEUE_CONNECTION=sync (phpunit.xml), PaymentClientJob corre inline.
    // ─────────────────────────────────────────────────────────────────

    public function test_cargo_completado_acredita_balance_y_crea_transaccion_credito(): void
    {
        $cliente       = $this->crearClientePortal();
        $montoInicial  = -500.00;
        $montoPago     = 399.00;

        $this->crearBalance($cliente->client_id, $montoInicial);

        $factura  = $this->crearFactura($cliente->client_id, [
            'estado' => 'Pendiente',
            'total'  => $montoPago,
        ]);

        $chargeId = 'trk_sandbox_recon_' . uniqid();

        $this->app->bind(OpenpayService::class, function () use ($chargeId, $montoPago) {
            $mock = $this->createMock(OpenpayService::class);
            $mock->expects($this->once())
                ->method('cobrarTarjeta')
                ->willReturn((object) [
                    'id'     => $chargeId,
                    'status' => 'completed',
                    'amount' => $montoPago,
                ]);
            return $mock;
        });

        $response = $this->actingAs($cliente, 'cliente')
            ->postJson("/portal/facturas/{$factura}/pagar", [
                'token'             => 'tok_test_recon',
                'device_session_id' => 'sess_test_recon',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        // ── 1. Payment creado via Eloquent (Payment::create, no DB::table) ───────
        $payment = \App\Models\Payment::where('receipt', $chargeId)->first();
        $this->assertNotNull($payment, 'Payment debe existir via Eloquent');
        $this->assertEquals($montoPago, (float) $payment->amount);
        $this->assertEquals($cliente->client_id, $payment->paymentable_id);
        $this->assertEquals('App\\Models\\Client', $payment->paymentable_type);

        // ── 2. Balance acreditado por PaymentClientJob (QUEUE_CONNECTION=sync) ──
        $balance = DB::table('balances')
            ->where('balanceable_id', $cliente->client_id)
            ->where('balanceable_type', 'App\\Models\\Client')
            ->first();
        $this->assertNotNull($balance, 'Debe existir registro de balance');
        $this->assertEquals(
            $montoInicial + $montoPago,
            (float) $balance->amount,
            "Balance esperado: {$montoInicial} + {$montoPago} = " . ($montoInicial + $montoPago) . ", obtenido: {$balance->amount}"
        );

        // ── 3. Transacción de crédito creada ────────────────────────────────────
        $transaction = DB::table('transactions')
            ->where('client_id', $cliente->client_id)
            ->where('type', 'credit')
            ->where('is_payment', 1)
            ->where('payment_id', $payment->id)
            ->first();
        $this->assertNotNull($transaction, 'Debe existir transacción type=credit, is_payment=1');
        $this->assertEquals($montoPago, (float) $transaction->credit);
        $this->assertEquals('Pago', $transaction->category);

        // ── 4. Pago visible en panel admin (misma query que ClientPaymentDatatableHelper) ─
        $count = \App\Models\Payment::where('paymentable_id', $cliente->client_id)->count();
        $this->assertEquals(1, $count, 'El pago debe aparecer en la lista del panel admin');
    }

    // ─────────────────────────────────────────────────────────────────
    // (g) Reintento del job NO duplica crédito ni balance.
    //     Simula un segundo disparo de PaymentClientJob para el mismo
    //     payment (como haría el queue worker con --tries=3).
    // ─────────────────────────────────────────────────────────────────

    public function test_reintento_del_job_no_duplica_credito_ni_balance(): void
    {
        $cliente      = $this->crearClientePortal();
        $montoInicial = -500.00;
        $montoPago    = 399.00;

        $this->crearBalance($cliente->client_id, $montoInicial);
        $factura = $this->crearFactura($cliente->client_id, ['estado' => 'Pendiente', 'total' => $montoPago]);
        $chargeId = 'trk_retry_' . uniqid();

        $this->app->bind(OpenpayService::class, function () use ($chargeId, $montoPago) {
            $mock = $this->createMock(OpenpayService::class);
            $mock->method('cobrarTarjeta')->willReturn((object) [
                'id'     => $chargeId,
                'status' => 'completed',
                'amount' => $montoPago,
            ]);
            return $mock;
        });

        // Primera ejecución: pago HTTP → Payment::create → observer → job (sync)
        $this->actingAs($cliente, 'cliente')
            ->postJson("/portal/facturas/{$factura}/pagar", [
                'token'             => 'tok_retry_test',
                'device_session_id' => 'sess_retry_test',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $payment = Payment::where('receipt', $chargeId)->firstOrFail();

        // Verificar estado tras primera ejecución (1 crédito, balance correcto)
        $this->assertEquals(1, DB::table('transactions')
            ->where('payment_id', $payment->id)->where('type', 'credit')->where('is_payment', 1)->count(),
            'Primera ejecución debe crear exactamente 1 crédito');

        $balanceTras1 = (float) DB::table('balances')
            ->where('balanceable_id', $cliente->client_id)->value('amount');
        $this->assertEquals($montoInicial + $montoPago, $balanceTras1);

        // ── Simular reintento del worker (disparo manual del mismo job) ──
        $job = new PaymentClientJob($payment, 'created');
        $job->handle(app(ClientRepository::class));

        // Sigue siendo exactamente 1 crédito y balance no cambió
        $this->assertEquals(1, DB::table('transactions')
            ->where('payment_id', $payment->id)->where('type', 'credit')->where('is_payment', 1)->count(),
            'El reintento NO debe crear un segundo crédito');

        $balanceTras2 = (float) DB::table('balances')
            ->where('balanceable_id', $cliente->client_id)->value('amount');
        $this->assertEquals($balanceTras1, $balanceTras2,
            'El balance no debe cambiar en el reintento');
    }

    // ─────────────────────────────────────────────────────────────────
    // (h) Pago admin: add_by válido → GeneralAccountingIncome creado
    //     sin crash aunque no haya sesión HTTP (simula worker de cola).
    // ─────────────────────────────────────────────────────────────────

    public function test_job_crea_accounting_income_sin_sesion_http(): void
    {
        $cliente  = $this->crearClientePortal();
        $addBy    = 1; // user sistema / admin mínimo para el test
        $this->crearBalance($cliente->client_id, 0.00);

        // Crear payment directamente (simula pago admin con add_by real)
        $paymentId = DB::table('payments')->insertGetId([
            'payment_method_id'      => 1,
            'date'                   => now()->toDateTimeString(),
            'amount'                 => 199.00,
            'payment_period'         => '',
            'comment'                => 'Pago admin test',
            'receipt'                => 'TEST-ADMIN-' . uniqid(),
            'send_receipt_after_payment' => 0,
            'add_by'                 => $addBy,
            'paymentable_id'         => $cliente->client_id,
            'paymentable_type'       => 'App\\Models\\Client',
            'is_first_payment'       => 0,
            'enabled_payment_promise'=> 0,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
        $payment = Payment::findOrFail($paymentId);

        // Dispatch directo del job (sin auth HTTP — simula worker de cola)
        $job = new PaymentClientJob($payment, 'created');
        $job->handle(app(ClientRepository::class));

        // Debe haber creado 1 crédito
        $this->assertEquals(1, DB::table('transactions')
            ->where('payment_id', $payment->id)->where('type', 'credit')->where('is_payment', 1)->count(),
            'Job de pago admin debe crear 1 transacción crédito');

        // Balance acreditado
        $balance = (float) DB::table('balances')
            ->where('balanceable_id', $cliente->client_id)->value('amount');
        $this->assertEquals(199.00, $balance);

        // GeneralAccountingIncome creado (no crash en auth sin sesión)
        $incomes = DB::table('general_accounting_incomes')
            ->where('payment_id', $payment->id)->whereNull('deleted_at')->count();
        $this->assertEquals(1, $incomes,
            'GeneralAccountingIncome debe crearse sin crash (auth()->id() fallback a add_by)');
    }

    // ─────────────────────────────────────────────────────────────────
    // (i) Garantía #2 — DB::transaction: crash dentro revierte TODO.
    //     Inyecta ClientBillingService que lanza excepción después de
    //     updateClientBalance + addTransaction. Verifica que el rollback
    //     deja transactions.count=0 y balance sin cambio (sin commit parcial).
    // ─────────────────────────────────────────────────────────────────

    public function test_crash_dentro_de_transaction_no_deja_commits_parciales(): void
    {
        $cliente      = $this->crearClientePortal();
        $montoInicial = -600.00;
        $montoPago    = 250.00;

        $this->crearBalance($cliente->client_id, $montoInicial);

        $paymentId = DB::table('payments')->insertGetId([
            'payment_method_id'          => 1,
            'date'                       => now()->toDateTimeString(),
            'amount'                     => $montoPago,
            'payment_period'             => '',
            'comment'                    => 'Pago test rollback',
            'receipt'                    => 'TEST-ROLLBACK-' . uniqid(),
            'send_receipt_after_payment' => 0,
            'add_by'                     => 1,
            'paymentable_id'             => $cliente->client_id,
            'paymentable_type'           => 'App\\Models\\Client',
            'is_first_payment'           => 0,
            'enabled_payment_promise'    => 0,
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ]);
        $payment = Payment::findOrFail($paymentId);

        // Inyectar billing que explota — simula GeneralAccountingService antes del fix
        $this->app->bind(ClientBillingService::class, function () {
            $mock = $this->createMock(ClientBillingService::class);
            $mock->method('billing')->willThrowException(new \RuntimeException('crash_test'));
            return $mock;
        });

        try {
            $job = new PaymentClientJob($payment, 'created');
            $job->handle(app(ClientRepository::class));
            $this->fail('Se esperaba RuntimeException del billing crash');
        } catch (\RuntimeException $e) {
            $this->assertEquals('crash_test', $e->getMessage());
        }

        // El DB::transaction debe haber revertido updateClientBalance + addTransaction
        $this->assertEquals(0,
            DB::table('transactions')
                ->where('payment_id', $payment->id)
                ->where('type', 'credit')
                ->where('is_payment', 1)
                ->count(),
            'Rollback debe revertir la transacción de crédito'
        );

        $balanceTras = (float) DB::table('balances')
            ->where('balanceable_id', $cliente->client_id)
            ->value('amount');
        $this->assertEquals($montoInicial, $balanceTras,
            'Rollback debe revertir la actualización de balance');
    }

    // ─────────────────────────────────────────────────────────────────
    // (f) Sin sesión → redirige al login (no 500)
    // ─────────────────────────────────────────────────────────────────

    public function test_sin_sesion_redirige_a_login(): void
    {
        $response = $this->postJson('/portal/facturas/99999/pagar', [
            'token'             => 'tok_fake',
            'device_session_id' => 'sess_fake',
        ]);

        // JSON request sin sesión → 401 o redirect
        $this->assertContains($response->getStatusCode(), [302, 401]);
    }
}
