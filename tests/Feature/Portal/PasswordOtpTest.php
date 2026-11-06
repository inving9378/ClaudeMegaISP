<?php

namespace Tests\Feature\Portal;

use App\Http\Middleware\VerifyCsrfToken;
use App\Modules\Addons\PortalCliente\Models\PortalClient;
use App\Modules\Addons\PortalCliente\Models\PortalPasswordOtp;
use App\Modules\Addons\PortalCliente\Services\AparienciaService;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

/**
 * Item #473 — recuperar contraseña con código por WhatsApp, detrás de portalcliente.recuperar_otp.
 *
 * Requiere portal_password_otps en la base de pruebas. El gateway se sustituye por un doble en
 * setUp(): ninguna prueba puede llegar a un envío real.
 */
class PasswordOtpTest extends PortalTestCase
{
    private const CONTRASENA_ORIGINAL = 'Original-473-a';
    private const TELEFONO = '5512345678';

    /** Envíos que recibió el doble: [slug, to, body, context]. */
    private array $enviados = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->mock(WhatsAppGateway::class, function ($doble) {
            $doble->shouldReceive('sendText')->andReturnUsing(function (?string $slug, string $to, string $body, array $context = []) {
                // Solo en pruebas: una misma app atiende varias peticiones y terminate() repite los callbacks anteriores.
                $envio = compact('slug', 'to', 'body', 'context');
                if (! in_array($envio, $this->enviados, true)) {
                    $this->enviados[] = $envio;
                }

                return new WhatsAppMessage();
            });
        });
    }

    private function encender(): void
    {
        config(['portalcliente.recuperar_otp' => true]);
    }

    private function cliente(array $datos = []): PortalClient
    {
        return $this->crearClientePortal($datos + ['phone' => self::TELEFONO, 'password' => self::CONTRASENA_ORIGINAL]);
    }

    private function otpVigente(PortalClient $cliente, string $codigo, array $datos = []): PortalPasswordOtp
    {
        return PortalPasswordOtp::create($datos + [
            'client_id'  => $cliente->client_id,
            'otp_hash'   => Hash::make($codigo),
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    private function enviarCodigo(PortalPasswordOtp $otp, string $codigo)
    {
        return $this->withSession(['portal_password_otp' => ['otp_id' => $otp->id, 'verified' => false]])
            ->post('/portal/password/verificar-otp', ['otp' => $codigo]);
    }

    private function restablecer(PortalPasswordOtp $otp, string $token, string $contrasena)
    {
        return $this->withSession(['portal_password_otp' => ['otp_id' => $otp->id, 'verified' => true, 'reset_token' => $token]])
            ->post('/portal/password/restablecer', ['contrasena' => $contrasena, 'contrasena_confirmation' => $contrasena]);
    }

    private function contrasenaLegacy(PortalClient $cliente): ?string
    {
        return DB::table('client_main_information')->where('id', $cliente->id)->value('password');
    }

    // ── Interruptor apagado (por defecto) ───────────────────────────────

    public function test_apagado_el_flujo_por_telefono_sigue_cambiando_la_contrasena_y_su_hash(): void
    {
        $this->assertFalse((bool) config('portalcliente.recuperar_otp'));
        $cliente = $this->cliente();

        $this->post('/portal/recuperar', [
            'numero_cliente'          => $cliente->client_id,
            'telefono'                => self::TELEFONO,
            'contrasena'              => 'Nueva-473-b',
            'contrasena_confirmation' => 'Nueva-473-b',
        ])->assertRedirect('/portal/login');

        $this->assertSame('Nueva-473-b', $this->contrasenaLegacy($cliente));
        $this->assertTrue(Hash::check('Nueva-473-b', $cliente->fresh()->web_password_hash));
        $this->assertSame([], $this->enviados);
    }

    public function test_apagado_la_pantalla_es_la_de_siempre(): void
    {
        $this->get('/portal/recuperar')->assertOk()
            ->assertSee('action="' . route('portal.recuperar.post') . '"', false)
            ->assertSee('name="contrasena_confirmation"', false)
            ->assertDontSee(route('portal.password.solicitar-otp'), false);
    }

    public function test_apagado_las_rutas_del_codigo_responden_404_y_no_envian(): void
    {
        $cliente = $this->cliente();

        $this->post('/portal/password/solicitar-otp', ['numero_cliente' => $cliente->client_id, 'telefono' => self::TELEFONO])->assertNotFound();
        $this->post('/portal/password/verificar-otp', ['otp' => '123456'])->assertNotFound();
        $this->post('/portal/password/restablecer', ['contrasena' => 'Nueva-473-c', 'contrasena_confirmation' => 'Nueva-473-c'])->assertNotFound();
        $this->get('/portal/recuperar/verificar')->assertNotFound();
        $this->get('/portal/recuperar/nueva')->assertNotFound();

        $this->assertSame([], $this->enviados);
        $this->assertSame(0, PortalPasswordOtp::where('client_id', $cliente->client_id)->count());
    }

    // ── Interruptor encendido ───────────────────────────────────────────

    public function test_encendido_el_post_de_un_solo_paso_no_cambia_la_contrasena(): void
    {
        $this->encender();
        $cliente = $this->cliente();

        $this->post('/portal/recuperar', [
            'numero_cliente'          => $cliente->client_id,
            'telefono'                => self::TELEFONO,
            'contrasena'              => 'Nueva-473-d',
            'contrasena_confirmation' => 'Nueva-473-d',
        ])->assertRedirect('/portal/recuperar')->assertSessionHas('info');

        $this->assertSame(self::CONTRASENA_ORIGINAL, $this->contrasenaLegacy($cliente));
        $this->assertNull($cliente->fresh()->web_password_hash);
    }

    public function test_encendido_la_pantalla_pide_el_codigo_sin_campos_de_contrasena(): void
    {
        $this->encender();

        $this->get('/portal/recuperar')->assertOk()
            ->assertSee('action="' . route('portal.password.solicitar-otp') . '"', false)
            ->assertSee('Enviarme un código por WhatsApp')
            ->assertDontSee('name="contrasena"', false);
    }

    public function test_flujo_completo_guarda_contrasena_y_hash_y_permite_entrar(): void
    {
        $this->encender();
        $cliente = $this->cliente();

        $this->post('/portal/password/solicitar-otp', ['numero_cliente' => $cliente->client_id, 'telefono' => self::TELEFONO])
            ->assertRedirect(route('portal.recuperar.verificar'));

        $this->assertCount(1, $this->enviados);
        $this->assertSame('521' . self::TELEFONO, $this->enviados[0]['to']);
        $this->assertMatchesRegularExpression('/\b\d{6}\b/', $this->enviados[0]['body']);
        preg_match('/\b(\d{6})\b/', $this->enviados[0]['body'], $m);

        $this->get('/portal/recuperar/verificar')->assertOk()->assertSee('name="otp"', false);

        $this->post('/portal/password/verificar-otp', ['otp' => $m[1]])->assertRedirect(route('portal.recuperar.nueva'));

        $this->get('/portal/recuperar/nueva')->assertOk()->assertSee('name="contrasena_confirmation"', false);

        $this->post('/portal/password/restablecer', ['contrasena' => 'Nueva-473-e', 'contrasena_confirmation' => 'Nueva-473-e'])
            ->assertRedirect(route('portal.login'))
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $this->assertSame('Nueva-473-e', $this->contrasenaLegacy($cliente));
        $this->assertTrue(Hash::check('Nueva-473-e', $cliente->fresh()->web_password_hash));

        $this->post('/portal/login', ['identificador' => (string) $cliente->client_id, 'contrasena' => 'Nueva-473-e'])
            ->assertRedirect('/portal');
        $this->assertAuthenticatedAs($cliente, 'cliente');
    }

    // ── Paso 1: el código se guarda con hash y no delata cuentas ────────

    public function test_el_codigo_se_guarda_solo_con_hash_y_caduca(): void
    {
        $this->encender();
        $cliente = $this->cliente();

        $this->post('/portal/password/solicitar-otp', ['numero_cliente' => $cliente->client_id, 'telefono' => self::TELEFONO]);

        preg_match('/\b(\d{6})\b/', $this->enviados[0]['body'], $m);
        $otp = PortalPasswordOtp::where('client_id', $cliente->client_id)->sole();

        $this->assertStringNotContainsString($m[1], json_encode($otp->getAttributes()));
        $this->assertTrue(Hash::check($m[1], $otp->otp_hash));
        $this->assertSame(0, $otp->attempts);
        $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, $otp->expires_at->timestamp, 5);
        $this->assertSame('*********5678', $otp->destino_enmascarado);
    }

    public function test_cuenta_inexistente_telefono_ajeno_y_correcto_responden_igual(): void
    {
        $this->encender();
        $cliente = $this->cliente();

        $casos = [
            'correcto'    => ['numero_cliente' => $cliente->client_id, 'telefono' => self::TELEFONO],
            'ajeno'       => ['numero_cliente' => $cliente->client_id, 'telefono' => '5599999999'],
            'inexistente' => ['numero_cliente' => 999999999, 'telefono' => self::TELEFONO],
        ];

        $respuestas = [];
        foreach ($casos as $nombre => $datos) {
            $respuesta = $this->post('/portal/password/solicitar-otp', $datos);
            $respuestas[$nombre] = [$respuesta->getStatusCode(), $respuesta->headers->get('Location'), session('success')];
        }

        $this->assertSame($respuestas['correcto'], $respuestas['ajeno']);
        $this->assertSame($respuestas['correcto'], $respuestas['inexistente']);
        $this->assertCount(1, $this->enviados);
        $this->assertSame(1, PortalPasswordOtp::where('client_id', $cliente->client_id)->count());
    }

    public function test_telefono_sin_digitos_no_coincide_con_los_campos_vacios_de_la_ficha(): void
    {
        $this->encender();
        $cliente = $this->cliente(['phone2' => null, 'phone3' => '']);

        $this->post('/portal/password/solicitar-otp', ['numero_cliente' => $cliente->client_id, 'telefono' => '---'])
            ->assertRedirect(route('portal.recuperar.verificar'));

        $this->assertSame([], $this->enviados);
        $this->assertSame(0, PortalPasswordOtp::where('client_id', $cliente->client_id)->count());
    }

    public function test_el_codigo_va_al_telefono_que_coincidio(): void
    {
        $this->encender();
        $cliente = $this->cliente(['phone' => '', 'phone2' => '5587654321']);

        $this->post('/portal/password/solicitar-otp', ['numero_cliente' => $cliente->client_id, 'telefono' => '55 8765 4321']);

        $this->assertSame('5215587654321', $this->enviados[0]['to']);
    }

    public function test_sin_fila_real_el_paso_dos_da_el_mismo_error(): void
    {
        $this->encender();

        $this->withSession(['portal_password_otp' => ['otp_id' => null, 'verified' => false]])
            ->post('/portal/password/verificar-otp', ['otp' => '123456'])
            ->assertRedirect(route('portal.recuperar.verificar'))
            ->assertSessionHasErrors(['otp' => 'Código incorrecto o expirado. Solicita uno nuevo si es necesario.']);
    }

    public function test_limite_de_solicitudes_por_cuenta_con_o_sin_cuenta_real(): void
    {
        $this->encender();
        $this->withoutMiddleware(ThrottleRequests::class);
        $cliente = $this->cliente();

        foreach ([$cliente->client_id, 999999998] as $numero) {
            for ($i = 0; $i < 3; $i++) {
                $this->post('/portal/password/solicitar-otp', ['numero_cliente' => $numero, 'telefono' => self::TELEFONO])
                    ->assertRedirect(route('portal.recuperar.verificar'));
            }
            $this->post('/portal/password/solicitar-otp', ['numero_cliente' => $numero, 'telefono' => self::TELEFONO])
                ->assertRedirect(route('portal.recuperar'))
                ->assertSessionHasErrors('numero_cliente');
        }

        $this->assertSame(3, PortalPasswordOtp::where('client_id', $cliente->client_id)->count());
    }

    public function test_limite_de_solicitudes_por_ip(): void
    {
        $this->encender();
        $this->withoutMiddleware(ThrottleRequests::class);

        for ($i = 0; $i < 10; $i++) {
            $this->post('/portal/password/solicitar-otp', ['numero_cliente' => 999990000 + $i, 'telefono' => self::TELEFONO])
                ->assertRedirect(route('portal.recuperar.verificar'));
        }

        $this->post('/portal/password/solicitar-otp', ['numero_cliente' => 999990010, 'telefono' => self::TELEFONO])
            ->assertRedirect(route('portal.recuperar'))
            ->assertSessionHasErrors('numero_cliente');
    }

    public function test_si_el_gateway_falla_la_respuesta_no_cambia(): void
    {
        $this->encender();
        $cliente = $this->cliente();

        $this->mock(WhatsAppGateway::class, function ($doble) {
            $doble->shouldReceive('sendText')->once()->andThrow(new \RuntimeException('sin línea'));
        });

        $this->post('/portal/password/solicitar-otp', ['numero_cliente' => $cliente->client_id, 'telefono' => self::TELEFONO])
            ->assertRedirect(route('portal.recuperar.verificar'))
            ->assertSessionHas('success');
    }

    // ── Paso 2: código incorrecto, caducado y bloqueo por intentos ──────

    public function test_codigo_incorrecto_gasta_un_intento_y_no_avanza(): void
    {
        $this->encender();
        $otp = $this->otpVigente($this->cliente(), '111111');

        $this->enviarCodigo($otp, '000000')
            ->assertRedirect(route('portal.recuperar.verificar'))
            ->assertSessionHasErrors('otp');

        $otp->refresh();
        $this->assertSame(1, $otp->attempts);
        $this->assertNull($otp->verified_at);
        $this->assertNull($otp->reset_token_hash);
    }

    public function test_codigo_caducado_no_se_acepta_aunque_sea_el_correcto(): void
    {
        $this->encender();
        $otp = $this->otpVigente($this->cliente(), '333333', ['expires_at' => now()->subMinute()]);

        $this->enviarCodigo($otp, '333333')
            ->assertRedirect(route('portal.recuperar.verificar'))
            ->assertSessionHasErrors('otp');

        $this->assertNull($otp->fresh()->verified_at);
    }

    public function test_agotar_los_intentos_bloquea_tambien_el_codigo_correcto(): void
    {
        $this->encender();
        $otp = $this->otpVigente($this->cliente(), '222222');

        for ($i = 0; $i < PortalPasswordOtp::MAX_ATTEMPTS; $i++) {
            $this->enviarCodigo($otp, '999999')->assertSessionHasErrors('otp');
        }
        $this->assertSame(PortalPasswordOtp::MAX_ATTEMPTS, $otp->fresh()->attempts);

        $this->enviarCodigo($otp, '222222')
            ->assertRedirect(route('portal.recuperar.verificar'))
            ->assertSessionHasErrors('otp');

        $otp->refresh();
        $this->assertSame(PortalPasswordOtp::MAX_ATTEMPTS, $otp->attempts);
        $this->assertNull($otp->verified_at);
    }

    public function test_codigo_ya_verificado_no_se_vuelve_a_aceptar(): void
    {
        $this->encender();
        $otp = $this->otpVigente($this->cliente(), '444444', ['verified_at' => now()]);

        $this->enviarCodigo($otp, '444444')->assertSessionHasErrors('otp');
    }

    public function test_codigo_correcto_emite_el_token_solo_en_sesion(): void
    {
        $this->encender();
        $otp = $this->otpVigente($this->cliente(), '555555');

        $respuesta = $this->enviarCodigo($otp, '555555')->assertRedirect(route('portal.recuperar.nueva'));

        $token = session('portal_password_otp')['reset_token'];
        $otp->refresh();
        $this->assertNotNull($otp->verified_at);
        $this->assertTrue(Hash::check($token, $otp->reset_token_hash));
        $this->assertStringNotContainsString($token, (string) $respuesta->headers->get('Location'));
    }

    // ── Paso 3: token de un solo uso ────────────────────────────────────

    private function otpVerificado(PortalClient $cliente, string $token, array $datos = []): PortalPasswordOtp
    {
        return $this->otpVigente($cliente, '666666', $datos + [
            'verified_at'            => now(),
            'reset_token_hash'       => Hash::make($token),
            'reset_token_expires_at' => now()->addMinutes(5),
        ]);
    }

    public function test_restablecer_guarda_ambas_columnas_e_invalida_el_token_y_audita_sin_secretos(): void
    {
        $this->encender();
        $cliente = $this->cliente();
        $otp = $this->otpVerificado($cliente, 'token-473');

        $this->restablecer($otp, 'token-473', 'Nueva-473-f')->assertRedirect(route('portal.login'))->assertSessionHasNoErrors();

        $this->assertSame('Nueva-473-f', $this->contrasenaLegacy($cliente));
        $this->assertTrue(Hash::check('Nueva-473-f', $cliente->fresh()->web_password_hash));
        $this->assertNotNull($otp->fresh()->reset_token_used_at);

        $log = DB::table('portal_profile_change_log')
            ->where('client_id', $cliente->client_id)->where('campo', 'password_reset_via_otp')->sole();
        $this->assertNull($log->valor_anterior);
        $this->assertNull($log->valor_nuevo);

        $this->restablecer($otp, 'token-473', 'Otra-473-g')->assertRedirect(route('portal.recuperar'))->assertSessionHasErrors('numero_cliente');
        $this->assertSame('Nueva-473-f', $this->contrasenaLegacy($cliente));
    }

    public function test_restablecer_con_token_caducado_no_cambia_nada(): void
    {
        $this->encender();
        $cliente = $this->cliente();
        $otp = $this->otpVerificado($cliente, 'token-473', ['reset_token_expires_at' => now()->subMinute()]);

        $this->restablecer($otp, 'token-473', 'Nueva-473-h')->assertSessionHasErrors('numero_cliente');

        $this->assertSame(self::CONTRASENA_ORIGINAL, $this->contrasenaLegacy($cliente));
    }

    public function test_restablecer_sin_haber_verificado_no_cambia_nada(): void
    {
        $this->encender();
        $cliente = $this->cliente();
        $otp = $this->otpVigente($cliente, '777777');

        $this->withSession(['portal_password_otp' => ['otp_id' => $otp->id, 'verified' => false]])
            ->post('/portal/password/restablecer', ['contrasena' => 'Nueva-473-i', 'contrasena_confirmation' => 'Nueva-473-i'])
            ->assertRedirect(route('portal.recuperar'))
            ->assertSessionHasErrors('numero_cliente');

        $this->get('/portal/recuperar/nueva')->assertRedirect(route('portal.recuperar'));
        $this->assertSame(self::CONTRASENA_ORIGINAL, $this->contrasenaLegacy($cliente));
    }

    // ── Rutas, impersonación y pantallas ────────────────────────────────

    public function test_todas_las_rutas_llevan_throttle_en_los_dos_montajes(): void
    {
        foreach (['portal.', 'portal_domain.'] as $montaje) {
            foreach (['password.solicitar-otp', 'recuperar.verificar', 'password.verificar-otp', 'recuperar.nueva', 'password.restablecer'] as $nombre) {
                $middleware = array_filter(Route::getRoutes()->getByName($montaje . $nombre)->gatherMiddleware(), 'is_string');
                $this->assertNotEmpty(preg_grep('/^throttle:\d+,1,portal-otp-/', $middleware), $montaje . $nombre);
            }
        }
    }

    public function test_el_throttle_de_ruta_corta_con_429_sin_gastar_el_contador_de_otra_ruta(): void
    {
        $this->encender();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/portal/password/solicitar-otp', ['numero_cliente' => 999980000 + $i, 'telefono' => self::TELEFONO])
                ->assertRedirect();
        }

        $this->post('/portal/password/solicitar-otp', ['numero_cliente' => 999980005, 'telefono' => self::TELEFONO])
            ->assertStatus(429);

        $this->withSession(['portal_password_otp' => ['otp_id' => null, 'verified' => false]])
            ->get('/portal/recuperar/verificar')->assertOk();
    }

    public function test_entrando_como_el_cliente_no_se_puede_usar_el_flujo(): void
    {
        $this->encender();
        $cliente = $this->cliente();
        $sesion = ['portal_impersonacion' => ['hasta' => now()->addMinutes(10)->timestamp]];

        foreach (['/portal/password/solicitar-otp', '/portal/password/verificar-otp', '/portal/password/restablecer'] as $url) {
            $this->actingAs($cliente, 'cliente')->withSession($sesion)
                ->post($url, ['numero_cliente' => $cliente->client_id, 'telefono' => self::TELEFONO, 'otp' => '123456'])
                ->assertRedirect(route('portal.dashboard'));
        }

        $this->assertSame([], $this->enviados);
    }

    public function test_las_pantallas_usan_la_plantilla_de_acceso_y_su_vista_previa(): void
    {
        $this->encender();
        $sesion = ['portal_password_otp' => ['otp_id' => null, 'verified' => true]];

        $this->withSession($sesion)->get('/portal/recuperar/verificar')->assertOk()
            ->assertSee('class="pl-card"', false)->assertSee(route('portal.password.verificar-otp'), false);
        $this->withSession($sesion)->get('/portal/recuperar/nueva')->assertOk()
            ->assertSee('class="pl-card"', false)->assertSee(route('portal.password.restablecer'), false);

        $previa = app(AparienciaService::class)->vista([], 'recuperar', ['fuente' => 'borrador']);
        foreach (['recuperar', 'recuperar-otp', 'recuperar-nueva'] as $pantalla) {
            $html = view("addon-portal-cliente::auth.{$pantalla}", ['apariencia' => $previa])->render();
            $this->assertStringContainsString('los formularios no envían datos', $html);
            $this->assertStringNotContainsString('autofocus', $html);
        }
    }
}
