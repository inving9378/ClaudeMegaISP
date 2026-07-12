<?php

namespace Tests\Feature\Payments;

use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use App\Modules\Addons\Payments\Services\Conciliation\PaymentFromSessionService;
use App\Modules\Addons\Payments\Support\ConciliationSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/**
 * Item #195 — blinda el CONTRATO del guardrail de dinero `auto_apply_enabled`
 * (ver docs/contratos/auto_apply.md). Read-only ya confirmó que se respeta;
 * este test evita que un refactor futuro lo rompa en silencio.
 *
 * USA DatabaseTransactions (no migrate:fresh) — mismo patrón que
 * tests/Feature/Portal/PortalTestCase.php, para correr contra el esquema real
 * de dev sin destruirlo. Todo se revierte al terminar cada test.
 */
class AutoApplyFlagContractTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const METHOD_TRANSFERENCIA = 2;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** Automático (confirmedBy=null) + certeza exacta + flag OFF → NO aplica. */
    public function test_automatic_path_is_blocked_when_flag_is_off(): void
    {
        ConciliationSettings::set('auto_apply_enabled', false);
        $clientId = $this->crearCliente();
        $session  = $this->crearSesionResuelta($clientId, Session::CERTAINTY_EXACT);

        $paymentsAntes = DB::table('payments')->count();

        $result = app(PaymentFromSessionService::class)->apply($session, null);

        $this->assertFalse($result['applied']);
        $this->assertSame('auto_apply_disabled', $result['reason']);
        $this->assertSame($paymentsAntes, DB::table('payments')->count(), 'No debe crear ningún payment si el flag está apagado');
        $this->assertNull($session->fresh()->applied_at);
    }

    /** Automático (confirmedBy=null) + certeza exacta + flag ON → SÍ aplica. */
    public function test_automatic_path_applies_when_flag_is_on(): void
    {
        ConciliationSettings::set('auto_apply_enabled', true);
        $clientId = $this->crearCliente();
        $session  = $this->crearSesionResuelta($clientId, Session::CERTAINTY_EXACT);

        $result = app(PaymentFromSessionService::class)->apply($session, null);

        $this->assertTrue($result['applied']);
        $this->assertSame('auto', $result['reason']);
        $this->assertTrue(DB::table('payments')->where('id', $result['payment_id'])->exists());

        $reported = DB::table('reported_payments')->where('id', $result['reported_payment_id'])->first();
        $this->assertNotNull($reported);
        $this->assertNull($reported->confirmed_by_user_id, 'La vía automática NO debe llevar confirmed_by (nadie confirmó a mano)');
    }

    /**
     * Confirmación humana (confirmedBy != null) + flag OFF → SÍ aplica.
     * Es el salto A PROPÓSITO documentado en el contrato: una decisión humana
     * explícita no depende del interruptor de automatización.
     */
    public function test_manual_confirmation_bypasses_the_flag_on_purpose(): void
    {
        ConciliationSettings::set('auto_apply_enabled', false);
        $clientId = $this->crearCliente();
        $session  = $this->crearSesionResuelta($clientId, Session::CERTAINTY_PROPOSED);
        $tereUserId = 1;

        $result = app(PaymentFromSessionService::class)->applyConfirmed($session->id, $tereUserId);

        $this->assertTrue($result['applied']);
        $this->assertSame('confirmed', $result['reason']);

        $reported = DB::table('reported_payments')->where('id', $result['reported_payment_id'])->first();
        $this->assertNotNull($reported);
        $this->assertEquals($tereUserId, $reported->confirmed_by_user_id);
    }

    /**
     * Certeza 'proposed' por vía automática se frena por RUTEO antes de
     * siquiera mirar el flag (candado independiente del freno maestro).
     */
    public function test_automatic_path_with_proposed_certainty_never_reaches_the_flag_check(): void
    {
        ConciliationSettings::set('auto_apply_enabled', true); // encendido a propósito
        $clientId = $this->crearCliente();
        $session  = $this->crearSesionResuelta($clientId, Session::CERTAINTY_PROPOSED);

        $result = app(PaymentFromSessionService::class)->apply($session, null);

        $this->assertFalse($result['applied']);
        $this->assertSame('needs_human_confirmation', $result['reason']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function crearCliente(): int
    {
        return DB::table('clients')->insertGetId([
            'created_by' => 1,
            'updated_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearSesionResuelta(int $clientId, string $certainty): Session
    {
        $extractionId = DB::table('whatsapp_payment_extractions')->insertGetId([
            'message_id'      => random_int(1000000, 9999999),
            'conversation_id' => random_int(1000000, 9999999),
            'document_type'   => 'spei_transfer',
            'ok'              => true,
            'fields'          => json_encode([
                'monto'          => ['value' => '150.00', 'confidence' => 'alta'],
                'clave_rastreo'  => ['value' => 'TEST' . uniqid(), 'confidence' => 'alta'],
                'titular_ordenante' => ['value' => 'CLIENTE DE PRUEBA', 'confidence' => 'alta'],
                'banco_origen'   => ['value' => 'BANCO TEST', 'confidence' => 'alta'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sessionId = DB::table('whatsapp_identification_sessions')->insertGetId([
            'extraction_id'       => $extractionId,
            'conversation_id'     => random_int(1000000, 9999999),
            'is_simulation'       => false,
            'state'               => Session::STATE_RESOLVED,
            'certainty'           => $certainty,
            'resolved_client_id'  => $clientId,
            'applied_at'          => null,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return Session::findOrFail($sessionId);
    }
}
