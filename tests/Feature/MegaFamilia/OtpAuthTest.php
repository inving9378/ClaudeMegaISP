<?php

namespace Tests\Feature\MegaFamilia;

use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Portal\PortalTestCase;

/**
 * #496: segundo factor por SMS. Twilio siempre va falseado con Http::fake: aquí no sale ningún SMS.
 */
class OtpAuthTest extends PortalTestCase
{
    private const ENVIAR = '/api/megafamilia/auth/otp/enviar';
    private const VERIFICAR = '/api/megafamilia/auth/otp/verificar';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.twilio' => ['sid' => 'sid-prueba', 'token' => 'token-prueba', 'from' => '+10000000000']]);
        Http::preventStrayRequests();
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'prueba'], 201)]);
    }

    private function crearFicha(array $campos = []): object
    {
        $usuarioWeb = 'mf496_' . uniqid();
        $cmi = $this->crearClientePortal(['user' => $usuarioWeb, 'password' => 'Web-496'] + $campos);

        return (object) ['id' => $cmi->id, 'usuario' => $usuarioWeb, 'client_id' => (int) $cmi->client_id];
    }

    private function crearCuenta(string $usuarioWeb, ?int $clientId): int
    {
        return DB::table('users')->insertGetId([
            'name'       => 'Prueba 496',
            'login_user' => $usuarioWeb,
            'client_id'  => $clientId === null ? null : (string) $clientId,
            'password'   => Hash::make('De-users-496'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function sembrarCodigo(object $ficha, string $codigo): int
    {
        return DB::table('parental_otp_codes')->insertGetId([
            'client_main_information_id' => $ficha->id,
            'destino'    => '+525500000000',
            'code_hash'  => hash('sha256', $codigo),
            'expira_at'  => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function enviar(object $ficha, string $password = 'Web-496')
    {
        return $this->postJson(self::ENVIAR, ['email' => $ficha->usuario, 'password' => $password]);
    }

    private function verificar(object $ficha, string $codigo, string $password = 'Web-496')
    {
        return $this->postJson(self::VERIFICAR, ['email' => $ficha->usuario, 'password' => $password, 'codigo' => $codigo]);
    }

    private function ultimoCodigoEnviado(): string
    {
        $peticion = Http::recorded()->last()[0];
        $this->assertInstanceOf(PeticionHttp::class, $peticion);
        $this->assertSame(1, preg_match('/\b(\d{6})\b/', $peticion['Body'], $m));

        return $m[1];
    }

    private function filasOtp(object $ficha): int
    {
        return DB::table('parental_otp_codes')->where('client_main_information_id', $ficha->id)->count();
    }

    public function test_cuenta_ajena_no_recibe_codigo_ni_token(): void
    {
        $otroCliente = $this->crearFicha();

        // Cuenta de otro cliente, y cuenta de personal (sin número de cliente ni rol) con el mismo usuario.
        foreach ([$otroCliente->client_id, null] as $clientIdDeLaCuenta) {
            $ficha = $this->crearFicha();
            $cuentaId = $this->crearCuenta($ficha->usuario, $clientIdDeLaCuenta);
            $otpId = $this->sembrarCodigo($ficha, '123456');

            $this->enviar($ficha)->assertStatus(401);
            $this->enviar($ficha, 'De-users-496')->assertStatus(401);
            $this->verificar($ficha, '123456')->assertStatus(401);
            $this->verificar($ficha, '123456', 'De-users-496')->assertStatus(401);

            $this->assertSame(1, $this->filasOtp($ficha));
            $this->assertNull(DB::table('parental_otp_codes')->where('id', $otpId)->value('verificado_at'));
            $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $cuentaId)->count());
        }

        Http::assertNothingSent();
    }

    public function test_sin_llaves_falla_limpio_y_no_deja_codigo_vigente(): void
    {
        config(['services.twilio' => ['sid' => null, 'token' => null, 'from' => null]]);
        $ficha = $this->crearFicha();

        $this->enviar($ficha)->assertStatus(422)->assertJsonPath('success', false);

        Http::assertNothingSent();
        $this->assertSame(0, $this->filasOtp($ficha));
    }

    public function test_si_twilio_rechaza_el_envio_no_queda_codigo_vigente(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'rechazado'], 400)]);
        $ficha = $this->crearFicha();

        $this->enviar($ficha)->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame(0, $this->filasOtp($ficha));
    }

    public function test_flujo_completo_con_cuenta_propia(): void
    {
        $ficha = $this->crearFicha();
        $cuentaId = $this->crearCuenta($ficha->usuario, $ficha->client_id);

        $this->enviar($ficha)->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('expira_en', 300)
            ->assertJsonPath('destino_oculto', '*********0000');
        $codigo = $this->ultimoCodigoEnviado();

        $this->assertSame(hash('sha256', $codigo), DB::table('parental_otp_codes')->where('client_main_information_id', $ficha->id)->value('code_hash'));

        $this->verificar($ficha, $codigo)->assertOk()->assertJsonPath('user.id', $cuentaId);
        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $cuentaId)->count());

        $this->verificar($ficha, $codigo)->assertStatus(422);
    }

    public function test_sin_cuenta_verificar_crea_la_cuenta_del_cliente(): void
    {
        $ficha = $this->crearFicha();

        $this->enviar($ficha)->assertOk();
        $this->verificar($ficha, $this->ultimoCodigoEnviado())->assertOk();

        $this->assertSame((string) $ficha->client_id, DB::table('users')->where('login_user', $ficha->usuario)->value('client_id'));
    }

    public function test_credenciales_malas_no_envian_ni_verifican(): void
    {
        $ficha = $this->crearFicha(['web_password_hash' => Hash::make('Anterior-496')]);
        $this->sembrarCodigo($ficha, '123456');

        $this->enviar($ficha, 'Incorrecta-496')->assertStatus(401);
        $this->enviar($ficha, 'Anterior-496')->assertStatus(401);
        $this->verificar($ficha, '123456', 'Anterior-496')->assertStatus(401);

        Http::assertNothingSent();
    }

    public function test_sin_telefono_valido_no_envia(): void
    {
        $ficha = $this->crearFicha(['phone' => '123']);

        $this->enviar($ficha)->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_tres_fallos_bloquean_y_pedir_otro_codigo_no_lo_esquiva(): void
    {
        $ficha = $this->crearFicha();
        $this->enviar($ficha)->assertOk();
        $codigo = $this->ultimoCodigoEnviado();
        $incorrecto = $codigo === '111111' ? '222222' : '111111';

        foreach (range(1, 3) as $_) {
            $this->verificar($ficha, $incorrecto)->assertStatus(422);
        }
        $this->verificar($ficha, $codigo)->assertStatus(422);

        $this->travel(2)->minutes();
        $this->enviar($ficha)->assertStatus(422);
        $this->assertSame(1, $this->filasOtp($ficha));
        Http::assertSentCount(1);

        $this->travel(15)->minutes();
        $this->enviar($ficha)->assertOk();
        $this->verificar($ficha, $this->ultimoCodigoEnviado())->assertOk();
    }

    public function test_reenvio_con_espera_y_el_codigo_anterior_deja_de_valer(): void
    {
        $ficha = $this->crearFicha();

        $this->enviar($ficha)->assertOk();
        $anterior = $this->ultimoCodigoEnviado();
        $this->enviar($ficha)->assertStatus(422);
        Http::assertSentCount(1);

        $this->travel(61)->seconds();
        $this->enviar($ficha)->assertOk();
        $nuevo = $this->ultimoCodigoEnviado();

        if ($anterior !== $nuevo) {
            $this->verificar($ficha, $anterior)->assertStatus(422);
        }
        $this->verificar($ficha, $nuevo)->assertOk();

        $this->verificar($ficha, $anterior)->assertStatus(422);
    }

    public function test_codigo_vencido_no_vale(): void
    {
        $ficha = $this->crearFicha();
        $this->enviar($ficha)->assertOk();
        $codigo = $this->ultimoCodigoEnviado();

        $this->travel(6)->minutes();

        $this->verificar($ficha, $codigo)->assertStatus(422);
    }

    public function test_los_endpoints_llevan_throttle(): void
    {
        $ficha = $this->crearFicha();

        foreach (range(1, 10) as $_) {
            $this->enviar($ficha, 'Incorrecta-496')->assertStatus(401);
        }

        $this->verificar($ficha, '123456', 'Incorrecta-496')->assertStatus(429);
    }
}
