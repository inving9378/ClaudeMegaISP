<?php

namespace Tests\Feature\FirmaElectronica;

use App\Modules\Addons\FirmaElectronica\Drivers\SandboxFirmaDriver;
use App\Modules\Addons\FirmaElectronica\Models\FirmaEvidencia;
use App\Modules\Addons\FirmaElectronica\Models\FirmaSolicitud;
use App\Modules\Addons\FirmaElectronica\Services\FirmaSolicitudService;
use App\Modules\Addons\FirmaElectronica\Services\FirmaTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

class FirmaElectronicaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private FirmaSolicitudService $svc;
    private FirmaTokenService $tok;

    protected function setUp(): void
    {
        parent::setUp();
        config(['firma_electronica.proveedor' => 'sandbox', 'firma_electronica.webhook_secreto' => 'secreto-de-prueba']);
        $this->svc = app(FirmaSolicitudService::class);
        $this->tok = app(FirmaTokenService::class);
    }

    private function enviada(int $firmantes = 1, bool $secuencial = false): array
    {
        $ps = [];
        for ($i = 1; $i <= $firmantes; $i++) {
            $ps[] = ['nombre' => "Firmante $i FICTICIO", 'correo' => "f$i@ejemplo.invalid"];
        }
        $s = $this->svc->crear($ps, null, $secuencial, null, hash('sha256', 'doc-ficticio'));
        $enlaces = $this->svc->enviar($s);
        return [$s->fresh(), array_values($enlaces)];
    }

    private function firmar(string $token, bool $consent = true)
    {
        $p = $this->tok->resolver($token);
        $otp = $this->tok->generarOtp($p);
        return $this->tok->firmar($token, $otp, 'Firmante FICTICIO', $consent, $this->svc);
    }

    public function test_envio_simulado(): void
    {
        [$s, $enl] = $this->enviada();
        $this->assertSame(FirmaSolicitud::ENVIADA, $s->estado);
        $this->assertStringStartsWith('sbx_', $s->id_externo);
        $this->assertSame(48, strlen($enl[0]));
        $this->assertNotSame($enl[0], $s->participantes->first()->token_hash); // se guarda hasheado
    }

    public function test_firma_parcial_y_completa(): void
    {
        [$s, $enl] = $this->enviada(2);
        $this->assertInstanceOf(FirmaEvidencia::class, $this->firmar($enl[0]));
        $this->assertSame(FirmaSolicitud::PARCIAL, $s->fresh()->estado);
        $this->firmar($enl[1]);
        $this->assertSame(FirmaSolicitud::FIRMADA, $s->fresh()->estado);
    }

    public function test_sin_consentimiento_no_firma(): void
    {
        [$s, $enl] = $this->enviada();
        $this->assertNull($this->firmar($enl[0], false));
        $this->assertSame(FirmaSolicitud::ENVIADA, $s->fresh()->estado);
    }

    public function test_otp_incorrecto_no_firma(): void
    {
        [$s, $enl] = $this->enviada();
        $p = $this->tok->resolver($enl[0]);
        $this->tok->generarOtp($p);
        $this->assertNull($this->tok->firmar($enl[0], '000000x', 'X', true, $this->svc));
    }

    public function test_enlace_reutilizado_falla_cerrado(): void
    {
        [$s, $enl] = $this->enviada(2);
        $this->firmar($enl[0]);
        $this->assertNull($this->tok->resolver($enl[0]));
    }

    public function test_enlace_vencido(): void
    {
        [$s, $enl] = $this->enviada();
        $s->participantes->first()->update(['expira_at' => now()->subMinute()]);
        $this->assertNull($this->tok->resolver($enl[0]));
        $this->assertNull($this->tok->resolver('x'));
    }

    public function test_orden_secuencial(): void
    {
        [$s, $enl] = $this->enviada(2, true);
        $this->assertNull($this->tok->resolver($enl[1]));
        $this->firmar($enl[0]);
        $this->assertNotNull($this->tok->resolver($enl[1]));
    }

    public function test_rechazo_y_estado_terminal(): void
    {
        [$s] = $this->enviada();
        $this->svc->aplicarEvento($s, 'e1', 'rechazada');
        $this->assertSame(FirmaSolicitud::RECHAZADA, $s->fresh()->estado);
        $this->svc->aplicarEvento($s, 'e2', 'abierta'); // tardío, no retrocede
        $this->assertSame(FirmaSolicitud::RECHAZADA, $s->fresh()->estado);
    }

    public function test_expiracion(): void
    {
        [$s] = $this->enviada();
        $s->update(['expira_at' => now()->subMinute()]);
        $this->assertSame(1, $this->svc->expirarVencidas());
        $this->assertSame(FirmaSolicitud::EXPIRADA, $s->fresh()->estado);
    }

    public function test_eventos_fuera_de_orden_y_duplicado(): void
    {
        [$s] = $this->enviada();
        $this->assertTrue($this->svc->aplicarEvento($s, 'a', 'abierta'));
        $this->svc->aplicarEvento($s, 'b', 'entregada'); // llega tarde: no retrocede
        $this->assertSame(FirmaSolicitud::ABIERTA, $s->fresh()->estado);
        $this->assertFalse($this->svc->aplicarEvento($s, 'a', 'abierta')); // duplicado
    }

    public function test_webhook_valido_e_invalido(): void
    {
        [$s] = $this->enviada();
        $cuerpo = json_encode(['id_externo' => $s->id_externo, 'evento_id' => 'w1', 'tipo' => 'entregada']);
        $firma = SandboxFirmaDriver::firmar($cuerpo, 'secreto-de-prueba');
        $h = ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Firma-Signature' => $firma];

        $this->call('POST', '/webhooks/firma-electronica', [], [], [], $h, $cuerpo)->assertOk()->assertJson(['duplicado' => false]);
        $this->assertSame(FirmaSolicitud::ENTREGADA, $s->fresh()->estado);
        $this->call('POST', '/webhooks/firma-electronica', [], [], [], $h, $cuerpo)->assertOk()->assertJson(['duplicado' => true]);

        $h['HTTP_X-Firma-Signature'] = 'invalida';
        $this->call('POST', '/webhooks/firma-electronica', [], [], [], $h, $cuerpo)->assertStatus(401);
    }
}
