<?php

namespace Tests\Feature\Portal;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Fase E — Tests de autenticación del Portal Cliente.
 *
 * Cubre: login (número/email), auto-registro (teléfono ok/ko), recuperar,
 * rate limiting, anti-enumeración, guard redirect, logout.
 *
 * Usa DatabaseTransactions → rollback automático, sin migrate:fresh.
 */
class AuthTest extends PortalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // POST en tests no envía CSRF token — deshabilitarlo solo en el contexto de test
        $this->withoutMiddleware(VerifyCsrfToken::class);
        // Limpiar rate limiters para que cada test parta limpio
        RateLimiter::clear('portal-login:127.0.0.1');
        RateLimiter::clear('portal-registro:127.0.0.1');
        RateLimiter::clear('portal-recuperar:127.0.0.1');
    }

    // ── LOGIN ─────────────────────────────────────────────────────────────

    public function test_login_por_numero_de_cliente(): void
    {
        $cliente = $this->crearClientePortal();

        $response = $this->post('/portal/login', [
            'identificador' => (string) $cliente->client_id,
            'contrasena'    => 'TestPassword123!',
        ]);

        $response->assertRedirect('/portal');
        $this->assertAuthenticatedAs($cliente, 'cliente');
    }

    public function test_login_por_email(): void
    {
        $cliente = $this->crearClientePortal(['email' => 'testlogin@portal.test']);

        $response = $this->post('/portal/login', [
            'identificador' => 'testlogin@portal.test',
            'contrasena'    => 'TestPassword123!',
        ]);

        $response->assertRedirect('/portal');
        $this->assertAuthenticatedAs($cliente, 'cliente');
    }

    public function test_login_actualiza_portal_last_login_at(): void
    {
        $cliente = $this->crearClientePortal();

        $this->post('/portal/login', [
            'identificador' => (string) $cliente->client_id,
            'contrasena'    => 'TestPassword123!',
        ]);

        $updatedAt = DB::table('client_main_information')
            ->where('id', $cliente->id)
            ->value('portal_last_login_at');

        $this->assertNotNull($updatedAt);
    }

    public function test_login_contrasena_incorrecta(): void
    {
        $cliente = $this->crearClientePortal();

        $response = $this->post('/portal/login', [
            'identificador' => (string) $cliente->client_id,
            'contrasena'    => 'WrongPassword!',
        ]);

        $response->assertSessionHasErrors('identificador');
        $this->assertGuest('cliente');
    }

    public function test_login_cliente_inexistente_no_revela_existencia(): void
    {
        // Anti-enumeración: mismo mensaje que contraseña incorrecta
        $response = $this->post('/portal/login', [
            'identificador' => '99999999',
            'contrasena'    => 'CualquierCosa1!',
        ]);

        $response->assertSessionHasErrors('identificador');
        // El error no dice "cliente no encontrado" — dice "credenciales incorrectas"
        $error = session('errors')->get('identificador')[0];
        $this->assertStringContainsString('Credenciales incorrectas', $error);
        $this->assertGuest('cliente');
    }

    public function test_login_rate_limit_despues_de_5_intentos(): void
    {
        $cliente = $this->crearClientePortal();

        // Agotar intentos
        for ($i = 0; $i < 5; $i++) {
            $this->post('/portal/login', [
                'identificador' => (string) $cliente->client_id,
                'contrasena'    => 'Wrong!',
            ]);
        }

        // El 6to debe ser bloqueado por rate limit
        $response = $this->post('/portal/login', [
            'identificador' => (string) $cliente->client_id,
            'contrasena'    => 'TestPassword123!',
        ]);

        $response->assertSessionHasErrors('identificador');
        $error = session('errors')->get('identificador')[0];
        $this->assertStringContainsString('Demasiados intentos', $error);
        $this->assertGuest('cliente');
    }

    // ── GUARD — REDIRECT SIN SESIÓN ───────────────────────────────────────

    public function test_ruta_protegida_redirige_a_login_sin_sesion(): void
    {
        $response = $this->get('/portal');
        $response->assertRedirect('/portal/login');
    }

    public function test_multiples_rutas_protegidas_redirigen_a_login(): void
    {
        $rutas = ['/portal/facturas', '/portal/pagos', '/portal/tickets', '/portal/consumo', '/portal/perfil'];
        foreach ($rutas as $ruta) {
            $this->get($ruta)->assertRedirect('/portal/login');
        }
    }

    // ── LOGOUT ───────────────────────────────────────────────────────────

    public function test_logout_cierra_sesion_y_redirige_a_login(): void
    {
        $cliente = $this->crearClientePortal();

        $this->actingAs($cliente, 'cliente')
            ->post('/portal/logout')
            ->assertRedirect('/portal/login');

        $this->assertGuest('cliente');
    }

    // ── AUTO-REGISTRO ─────────────────────────────────────────────────────

    public function test_registro_con_telefono_correcto_crea_cuenta_y_autentica(): void
    {
        // Cliente sin portal_password (aún no registrado)
        $clientId = DB::table('clients')->insertGetId([
            'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('client_main_information')->insertGetId([
            'client_id'        => $clientId,
            'name'             => 'RegistroTest',
            'father_last_name' => 'Apellido',
            'phone'            => '5512345678',
            'portal_password'  => null,
            'created_at'       => now(), 'updated_at' => now(),
        ]);

        $response = $this->post('/portal/registro', [
            'numero_cliente'            => $clientId,
            'telefono'                  => '5512345678',
            'contrasena'                => 'NuevaPass123!',
            'contrasena_confirmation'   => 'NuevaPass123!',
        ]);

        $response->assertRedirect('/portal');

        // Verificar que se guardó el hash
        $hash = DB::table('client_main_information')
            ->where('client_id', $clientId)
            ->value('portal_password');
        $this->assertTrue(Hash::check('NuevaPass123!', $hash));
    }

    public function test_registro_telefono_incorrecto_da_mensaje_generico(): void
    {
        // Cliente sin portal_password
        $clientId = DB::table('clients')->insertGetId([
            'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('client_main_information')->insertGetId([
            'client_id'        => $clientId,
            'name'             => 'ClienteReg',
            'father_last_name' => 'Apellido',
            'phone'            => '5512345678',
            'portal_password'  => null,
            'created_at'       => now(), 'updated_at' => now(),
        ]);

        $response = $this->post('/portal/registro', [
            'numero_cliente'            => $clientId,
            'telefono'                  => '9999999999',  // teléfono incorrecto
            'contrasena'                => 'NuevaPass123!',
            'contrasena_confirmation'   => 'NuevaPass123!',
        ]);

        $response->assertSessionHasErrors('numero_cliente');
        // Mensaje genérico — no revela si el cliente existe
        $error = session('errors')->get('numero_cliente')[0];
        $this->assertStringContainsString('No encontramos', $error);
        $this->assertGuest('cliente');
    }

    public function test_registro_cliente_ya_registrado_redirige_a_recuperar(): void
    {
        $cliente = $this->crearClientePortal(); // ya tiene portal_password

        $response = $this->post('/portal/registro', [
            'numero_cliente'            => $cliente->client_id,
            'telefono'                  => '5500000000',
            'contrasena'                => 'NuevaPass123!',
            'contrasena_confirmation'   => 'NuevaPass123!',
        ]);

        $response->assertSessionHasErrors('numero_cliente');
        $error = session('errors')->get('numero_cliente')[0];
        $this->assertStringContainsString('ya tiene cuenta', $error);
    }

    public function test_registro_numero_inexistente_no_revela_existencia(): void
    {
        $response = $this->post('/portal/registro', [
            'numero_cliente'            => 99999999,
            'telefono'                  => '5512345678',
            'contrasena'                => 'NuevaPass123!',
            'contrasena_confirmation'   => 'NuevaPass123!',
        ]);

        $response->assertSessionHasErrors('numero_cliente');
        $error = session('errors')->get('numero_cliente')[0];
        $this->assertStringContainsString('No encontramos', $error);
    }

    public function test_registro_rate_limit_despues_de_5_intentos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/portal/registro', [
                'numero_cliente'            => 99999999,
                'telefono'                  => '0000000000',
                'contrasena'                => 'Pass1234!',
                'contrasena_confirmation'   => 'Pass1234!',
            ]);
        }

        $response = $this->post('/portal/registro', [
            'numero_cliente'            => 99999999,
            'telefono'                  => '0000000000',
            'contrasena'                => 'Pass1234!',
            'contrasena_confirmation'   => 'Pass1234!',
        ]);

        $response->assertSessionHasErrors('numero_cliente');
        $error = session('errors')->get('numero_cliente')[0];
        $this->assertStringContainsString('Demasiados intentos', $error);
    }

    // ── RECUPERAR CONTRASEÑA ──────────────────────────────────────────────

    public function test_recuperar_contrasena_con_telefono_correcto(): void
    {
        $cliente = $this->crearClientePortal();

        $response = $this->post('/portal/recuperar', [
            'numero_cliente'            => $cliente->client_id,
            'telefono'                  => '5500000000',
            'contrasena'                => 'NuevaPass456!',
            'contrasena_confirmation'   => 'NuevaPass456!',
        ]);

        $response->assertRedirect('/portal/login');

        // La nueva contraseña debe funcionar
        $hash = DB::table('client_main_information')
            ->where('id', $cliente->id)
            ->value('portal_password');
        $this->assertTrue(Hash::check('NuevaPass456!', $hash));
    }

    public function test_recuperar_contrasena_telefono_incorrecto(): void
    {
        $cliente = $this->crearClientePortal();

        $response = $this->post('/portal/recuperar', [
            'numero_cliente'            => $cliente->client_id,
            'telefono'                  => '0000000000',
            'contrasena'                => 'NuevaPass456!',
            'contrasena_confirmation'   => 'NuevaPass456!',
        ]);

        $response->assertSessionHasErrors('numero_cliente');

        // La contraseña original sigue igual
        $hash = DB::table('client_main_information')
            ->where('id', $cliente->id)
            ->value('portal_password');
        $this->assertTrue(Hash::check('TestPassword123!', $hash));
    }
}
