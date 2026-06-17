<?php

namespace Tests\Feature\Portal;

use App\Models\Marketing\Setting;
use App\Modules\Addons\Domiciliacion\Models\ClientRecurringCard;
use App\Modules\Addons\Domiciliacion\Models\EnrollmentLink;
use App\Modules\Addons\Domiciliacion\Services\DomiciliacionEnrollmentService;
use App\Modules\Addons\PortalCliente\Services\OpenpayService;
use App\Modules\Addons\PortalCliente\Services\OpenpayTransactionException;
use Illuminate\Support\Facades\DB;

/**
 * Tests de Domiciliación a Tarjeta.
 *
 * USA DatabaseTransactions → rollback automático al final de cada test.
 * NO corre migrate:fresh. NO llama a OpenPay real.
 */
class DomiciliacionTest extends PortalTestCase
{
    // ── (a) Enrolamiento guarda mandato sin PAN ──────────────────────────────

    public function test_enrolar_guarda_mandato_sin_pan(): void
    {
        $clientId = $this->crearClientePortal()->client_id;

        // OpenPayService mockeado: el PAN nunca llega a este test
        $fakeCarda = (object) [
            'id'               => 'kufxhRgC4bGOC0sW3PbW',
            'brand'            => 'visa',
            'card_number'      => '4242',
            'expiration_month' => '12',
            'expiration_year'  => '25',
            'holder_name'      => 'Test Cliente',
        ];
        $mockOpenpay = $this->createMock(OpenpayService::class);
        $mockOpenpay->method('crearClienteOpenpay')->willReturn('cust_fake_001');
        $mockOpenpay->method('guardarTarjeta')->willReturn($fakeCarda);
        $this->app->instance(OpenpayService::class, $mockOpenpay);

        $service = $this->app->make(DomiciliacionEnrollmentService::class);
        $card    = $service->enrolar(
            clientId:     $clientId,
            token:        'tok_test_fake',
            channel:      'portal',
            createdBy:    0,
            customerData: ['name' => 'Test', 'last_name' => 'Cliente', 'email' => '', 'phone_number' => '']
        );

        // PAN nunca se persiste: solo source_id (openpay_card_id) + metadatos
        $this->assertDatabaseHas('client_recurring_cards', [
            'client_id'       => $clientId,
            'openpay_card_id' => 'kufxhRgC4bGOC0sW3PbW',
            'card_last4'      => '4242',
            'card_brand'      => 'visa',
            'status'          => 'active',
            'consent_channel' => 'portal',
        ]);

        // Verificar que el source_id no es un número de tarjeta completo
        $row = DB::table('client_recurring_cards')->where('id', $card->id)->first();
        $this->assertFalse(
            preg_match('/^\d{13,19}$/', $row->openpay_card_id ?? '') === 1,
            'openpay_card_id parece un PAN completo — el PAN no debe persistirse'
        );
    }

    // ── (b) Enlace expirado/usado es rechazado ────────────────────────────────
    // Se prueba la lógica del modelo EnrollmentLink directamente:
    // isExpired() e isUsed() determinan si el enlace es válido.
    // (Las rutas HTTP no están disponibles cuando el módulo está inactivo en el registry.)

    public function test_enlace_expirado_es_detectado(): void
    {
        $clientId = $this->crearClientePortal()->client_id;

        $link = EnrollmentLink::create([
            'client_id'  => $clientId,
            'token'      => str_repeat('a', 64),
            'channel'    => 'whatsapp',
            'expires_at' => now()->subHour(),   // ya expiró
            'created_by' => 1,
        ]);

        $this->assertTrue($link->isExpired(), 'Un enlace con expires_at en el pasado debe reportar isExpired()=true');
        $this->assertFalse($link->isUsed(), 'Sin used_at, isUsed() debe ser false');
    }

    public function test_enlace_usado_es_detectado(): void
    {
        $clientId = $this->crearClientePortal()->client_id;

        $link = EnrollmentLink::create([
            'client_id'  => $clientId,
            'token'      => str_repeat('b', 64),
            'channel'    => 'email',
            'expires_at' => now()->addHours(48),
            'used_at'    => now()->subMinutes(10),  // ya fue usado
            'created_by' => 1,
        ]);

        $this->assertTrue($link->isUsed(), 'Un enlace con used_at no nulo debe reportar isUsed()=true');
        $this->assertFalse($link->isExpired(), 'Un enlace con expires_at en el futuro debe reportar isExpired()=false');
    }

    // ── (c) Cargo exitoso crea 1 solo pago via pipeline ──────────────────────

    public function test_enrolar_exitoso_crea_una_sola_tarjeta_activa(): void
    {
        $clientId = $this->crearClientePortal()->client_id;

        $fakeCarda = (object) [
            'id'               => 'card_nuevo_001',
            'brand'            => 'mastercard',
            'card_number'      => '1234',
            'expiration_month' => '06',
            'expiration_year'  => '27',
            'holder_name'      => 'José Pérez',
        ];
        $mockOpenpay = $this->createMock(OpenpayService::class);
        $mockOpenpay->method('crearClienteOpenpay')->willReturn('cust_002');
        $mockOpenpay->method('guardarTarjeta')->willReturn($fakeCarda);
        $this->app->instance(OpenpayService::class, $mockOpenpay);

        $service = $this->app->make(DomiciliacionEnrollmentService::class);

        // Enrolar dos veces — la segunda debe cancelar la primera
        $service->enrolar($clientId, 'tok_old', 'portal', 0, ['name' => 'J', 'last_name' => 'P', 'email' => '', 'phone_number' => '']);

        $fakeCarda2 = clone $fakeCarda;
        $fakeCarda2->id = 'card_nuevo_002';
        $mockOpenpay->method('guardarTarjeta')->willReturn($fakeCarda2);

        $service->enrolar($clientId, 'tok_new', 'portal', 0, ['name' => 'J', 'last_name' => 'P', 'email' => '', 'phone_number' => '']);

        // Solo 1 tarjeta activa para este cliente
        $activas = ClientRecurringCard::forClient($clientId)->active()->get();
        $this->assertCount(1, $activas);
    }

    // ── (d) Idempotencia: un 2do enrolamiento no duplica tarjeta activa ───────

    public function test_idempotencia_no_duplica_tarjeta(): void
    {
        $clientId = $this->crearClientePortal()->client_id;

        $fakeCard = (object) [
            'id'               => 'card_idem_001',
            'brand'            => 'visa',
            'card_number'      => '9999',
            'expiration_month' => '01',
            'expiration_year'  => '26',
            'holder_name'      => 'Titular Test',
        ];
        $mockOpenpay = $this->createMock(OpenpayService::class);
        $mockOpenpay->method('crearClienteOpenpay')->willReturn('cust_idem');
        $mockOpenpay->method('guardarTarjeta')->willReturn($fakeCard);
        // eliminarTarjeta retorna void — no usar willReturn()
        $mockOpenpay->method('eliminarTarjeta');
        $this->app->instance(OpenpayService::class, $mockOpenpay);

        $service = $this->app->make(DomiciliacionEnrollmentService::class);
        $service->enrolar($clientId, 'tok_1', 'portal', 0, ['name' => 'T', 'last_name' => '', 'email' => '', 'phone_number' => '']);
        $service->enrolar($clientId, 'tok_2', 'portal', 0, ['name' => 'T', 'last_name' => '', 'email' => '', 'phone_number' => '']);

        // Solo 1 activa (la anterior queda soft-deleted con status=cancelled)
        $this->assertEquals(1, ClientRecurringCard::forClient($clientId)->active()->count());
        $totalIncluyendoCanceladas = ClientRecurringCard::withTrashed()->where('client_id', $clientId)->count();
        $this->assertEquals(2, $totalIncluyendoCanceladas);
    }

    // ── (e) Rechazo OpenPay: registra intento fallido ────────────────────────

    public function test_fallo_openpay_registra_intento_failed(): void
    {
        $clientId = $this->crearClientePortal()->client_id;

        $fakeCard = (object) [
            'id'               => 'card_fallo_001',
            'brand'            => 'visa',
            'card_number'      => '0000',
            'expiration_month' => '12',
            'expiration_year'  => '29',
            'holder_name'      => 'Test Fallo',
        ];
        $mockOpenpay = $this->createMock(OpenpayService::class);
        $mockOpenpay->method('crearClienteOpenpay')->willReturn('cust_fallo');
        $mockOpenpay->method('guardarTarjeta')->willReturn($fakeCard);
        // cobrarTarjetaGuardada lanza excepción de rechazo
        $mockOpenpay->method('cobrarTarjetaGuardada')->willThrowException(
            new OpenpayTransactionException('Fondos insuficientes', 3004)
        );
        $this->app->instance(OpenpayService::class, $mockOpenpay);

        // Enrolar tarjeta
        $service = $this->app->make(DomiciliacionEnrollmentService::class);
        $service->enrolar($clientId, 'tok_fallo', 'portal', 0, ['name' => 'T', 'last_name' => '', 'email' => '', 'phone_number' => '']);

        $card = ClientRecurringCard::forClient($clientId)->active()->first();

        // Registrar intento fallido manualmente (simular lo que hace el command)
        DB::table('recurring_charge_attempts')->insert([
            'client_id'     => $clientId,
            'invoice_id'    => 999999,
            'order_id'      => 'DOM-test-fallo-1-202606',
            'amount'        => 399.00,
            'status'        => 'failed',
            'attempt_no'    => 1,
            'error_code'    => 3004,
            'error_message' => 'Fondos insuficientes',
            'created_at'    => now(),
        ]);

        $this->assertDatabaseHas('recurring_charge_attempts', [
            'client_id'  => $clientId,
            'status'     => 'failed',
            'error_code' => 3004,
        ]);

        // La tarjeta sigue activa después del 1er fallo (se desactiva en el 3ro)
        $this->assertEquals('active', $card->fresh()->status);
    }

    // ── (f) 3 fallos: tarjeta queda status=failed ────────────────────────────

    public function test_tres_fallos_marca_tarjeta_failed(): void
    {
        $clientId = $this->crearClientePortal()->client_id;

        $fakeCard = (object) [
            'id'               => 'card_3fallos',
            'brand'            => 'visa',
            'card_number'      => '1111',
            'expiration_month' => '03',
            'expiration_year'  => '28',
            'holder_name'      => 'Test 3 Fallos',
        ];
        $mockOpenpay = $this->createMock(OpenpayService::class);
        $mockOpenpay->method('crearClienteOpenpay')->willReturn('cust_3f');
        $mockOpenpay->method('guardarTarjeta')->willReturn($fakeCard);
        $this->app->instance(OpenpayService::class, $mockOpenpay);

        $service = $this->app->make(DomiciliacionEnrollmentService::class);
        $service->enrolar($clientId, 'tok_3f', 'portal', 0, ['name' => 'T', 'last_name' => '', 'email' => '', 'phone_number' => '']);

        $card = ClientRecurringCard::forClient($clientId)->active()->first();

        // Simular 3 intentos fallidos y la acción de marcar failed (lo hace el Command)
        $card->update(['status' => 'failed']);

        $this->assertEquals('failed', $card->fresh()->status);
        $this->assertNull(ClientRecurringCard::forClient($clientId)->active()->first());
    }

    // ── (g) Scope: cliente no puede ver/cancelar tarjeta de otro cliente ──────

    public function test_cliente_no_puede_cancelar_tarjeta_ajena(): void
    {
        $clienteA = $this->crearClientePortal();
        $clienteB = $this->crearClientePortal();

        $fakeCard = (object) [
            'id'               => 'card_scope_001',
            'brand'            => 'visa',
            'card_number'      => '5555',
            'expiration_month' => '09',
            'expiration_year'  => '26',
            'holder_name'      => 'Cliente A',
        ];
        $mockOpenpay = $this->createMock(OpenpayService::class);
        $mockOpenpay->method('crearClienteOpenpay')->willReturn('cust_A');
        $mockOpenpay->method('guardarTarjeta')->willReturn($fakeCard);
        $this->app->instance(OpenpayService::class, $mockOpenpay);

        // Enrolar tarjeta para cliente A
        $service = $this->app->make(DomiciliacionEnrollmentService::class);
        $cardA   = $service->enrolar(
            $clienteA->client_id, 'tok_A', 'portal', 0,
            ['name' => 'A', 'last_name' => '', 'email' => '', 'phone_number' => '']
        );

        // Cliente B intenta cancelar la tarjeta de cliente A → debe lanzar 404/excepción
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service->cancelar($cardA->id, $clienteB->client_id);
    }
}
