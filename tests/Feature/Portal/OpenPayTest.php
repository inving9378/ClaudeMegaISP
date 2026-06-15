<?php

namespace Tests\Feature\Portal;

use App\Http\Middleware\VerifyCsrfToken;
use App\Modules\Addons\PortalCliente\Services\OpenpayService;
use App\Modules\Addons\PortalCliente\Services\OpenpayTransactionException;
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
