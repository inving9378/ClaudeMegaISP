<?php

namespace Tests\Feature\Portal;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Un teléfono sin dígitos se normaliza a '' y coincidía con cualquier phone2/phone3 vacío: bastaba el
 * número de cliente para cambiar su contraseña. Con el flujo por código encendido, registro tampoco
 * debe servir para cambiar la contraseña de una cuenta ya registrada.
 */
class TelefonoSinDigitosTest extends PortalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        RateLimiter::clear('portal-recuperar:127.0.0.1');
        RateLimiter::clear('portal-registro:127.0.0.1');
    }

    private function cliente()
    {
        return $this->crearClientePortal([
            'password' => 'Original-12345',
            'phone'    => '5512345678',
            'phone2'   => null,
            'phone3'   => '',
        ]);
    }

    private function datos($cliente, string $telefono): array
    {
        return [
            'numero_cliente'          => $cliente->client_id,
            'telefono'                => $telefono,
            'contrasena'              => 'Atacante-98765',
            'contrasena_confirmation' => 'Atacante-98765',
        ];
    }

    private function contrasena($cliente): ?string
    {
        return DB::table('client_main_information')->where('client_id', $cliente->client_id)->value('password');
    }

    /** @dataProvider telefonosSinDigitos */
    public function test_recuperar_no_cambia_la_contrasena(string $telefono): void
    {
        config(['portalcliente.recuperar_otp' => false]);
        $cliente = $this->cliente();

        $this->post('/portal/recuperar', $this->datos($cliente, $telefono));

        $this->assertSame('Original-12345', $this->contrasena($cliente));
    }

    /** @dataProvider telefonosSinDigitos */
    public function test_registro_no_cambia_la_contrasena_ni_inicia_sesion(string $telefono): void
    {
        config(['portalcliente.recuperar_otp' => false]);
        $cliente = $this->cliente();

        $this->post('/portal/registro', $this->datos($cliente, $telefono));

        $this->assertSame('Original-12345', $this->contrasena($cliente));
        $this->assertGuest('cliente');
    }

    public static function telefonosSinDigitos(): array
    {
        return ['letras' => ['abc'], 'simbolos' => ['---'], 'espacio' => [' ']];
    }

    public function test_el_telefono_correcto_sigue_funcionando_con_el_flujo_apagado(): void
    {
        config(['portalcliente.recuperar_otp' => false]);
        $cliente = $this->cliente();

        $this->post('/portal/recuperar', $this->datos($cliente, '55-1234-5678'));

        $this->assertSame('Atacante-98765', $this->contrasena($cliente));
    }

    public function test_con_codigo_encendido_registro_no_cambia_la_contrasena_de_cuenta_registrada(): void
    {
        config(['portalcliente.recuperar_otp' => true]);
        $cliente = $this->cliente();

        $this->post('/portal/registro', $this->datos($cliente, '5512345678'))
            ->assertRedirect('/portal/recuperar');

        $this->assertSame('Original-12345', $this->contrasena($cliente));
        $this->assertGuest('cliente');
    }

    public function test_con_codigo_encendido_registro_sigue_sirviendo_a_quien_no_tiene_cuenta(): void
    {
        config(['portalcliente.recuperar_otp' => true]);
        $cliente = $this->crearClientePortal(['password' => null, 'phone' => '5512345678', 'portal_registered_at' => null]);

        $this->post('/portal/registro', $this->datos($cliente, '5512345678'));

        $this->assertSame('Atacante-98765', $this->contrasena($cliente));
    }
}
