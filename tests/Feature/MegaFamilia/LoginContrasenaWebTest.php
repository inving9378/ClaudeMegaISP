<?php

namespace Tests\Feature\MegaFamilia;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Portal\PortalTestCase;

/**
 * #251: login móvil con la Contraseña WEB de la ficha, misma regla que el portal (#1155).
 */
class LoginContrasenaWebTest extends PortalTestCase
{
    private function crearFicha(array $campos): object
    {
        $usuarioWeb = 'mf251_' . uniqid();
        $cmi = $this->crearClientePortal(['user' => $usuarioWeb] + $campos);

        return (object) ['usuario' => $usuarioWeb, 'client_id' => (int) $cmi->client_id];
    }

    private function crearCuenta(string $usuarioWeb, int $clientId, string $password): int
    {
        return DB::table('users')->insertGetId([
            'name'       => 'Prueba 251',
            'login_user' => $usuarioWeb,
            'client_id'  => (string) $clientId,
            'password'   => Hash::make($password),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function hash(object $ficha): ?string
    {
        return DB::table('client_main_information')->where('client_id', $ficha->client_id)->value('web_password_hash');
    }

    private function entrar(object $ficha, string $password)
    {
        return $this->postJson('/api/megafamilia/auth/login', ['email' => $ficha->usuario, 'password' => $password]);
    }

    public function test_sin_cuenta_la_legacy_correcta_crea_la_cuenta_y_guarda_el_hash(): void
    {
        $ficha = $this->crearFicha(['password' => 'Legacy-251-a']);

        $this->entrar($ficha, 'Legacy-251-a')->assertOk()->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('Legacy-251-a', $this->hash($ficha)));
        $this->assertSame(1, DB::table('users')->where('login_user', $ficha->usuario)->count());
    }

    public function test_con_cuenta_la_legacy_correcta_guarda_el_hash(): void
    {
        $ficha = $this->crearFicha(['password' => 'Legacy-251-b']);
        $this->crearCuenta($ficha->usuario, $ficha->client_id, 'Otra-de-users-251');

        $this->entrar($ficha, 'Legacy-251-b')->assertOk();

        $this->assertTrue(Hash::check('Legacy-251-b', $this->hash($ficha)));
    }

    public function test_contrasena_anterior_no_entra_si_la_legacy_cambio(): void
    {
        foreach ([false, true] as $conCuenta) {
            $ficha = $this->crearFicha(['password' => 'Nueva-251-c', 'web_password_hash' => Hash::make('Anterior-251-c')]);
            if ($conCuenta) {
                $this->crearCuenta($ficha->usuario, $ficha->client_id, 'Otra-de-users-251');
            }

            $this->entrar($ficha, 'Anterior-251-c')->assertStatus(401);
            $this->assertTrue(Hash::check('Anterior-251-c', $this->hash($ficha)));

            $this->entrar($ficha, 'Nueva-251-c')->assertOk();
            $this->assertTrue(Hash::check('Nueva-251-c', $this->hash($ficha)));
        }
    }

    public function test_sin_legacy_decide_el_hash(): void
    {
        foreach ([false, true] as $conCuenta) {
            $ficha = $this->crearFicha(['password' => null, 'web_password_hash' => Hash::make('Solo-Hash-251-d')]);
            if ($conCuenta) {
                $this->crearCuenta($ficha->usuario, $ficha->client_id, 'Otra-de-users-251');
            }
            $hashInicial = $this->hash($ficha);

            $this->entrar($ficha, 'Incorrecta-251-d')->assertStatus(401);
            $this->entrar($ficha, 'Solo-Hash-251-d')->assertOk();
            $this->assertSame($hashInicial, $this->hash($ficha));
        }
    }

    public function test_sin_legacy_ni_hash_no_entra(): void
    {
        $ficha = $this->crearFicha(['password' => null]);

        $this->entrar($ficha, 'Cualquiera-251-e')->assertStatus(401);
        $this->assertSame(0, DB::table('users')->where('login_user', $ficha->usuario)->count());
    }

    public function test_contrasena_incorrecta_no_guarda_hash(): void
    {
        $ficha = $this->crearFicha(['password' => 'Legacy-251-f']);

        $this->entrar($ficha, 'Incorrecta-251-f')->assertStatus(401);

        $this->assertNull($this->hash($ficha));
    }

    public function test_entrar_con_la_de_users_no_escribe_el_hash(): void
    {
        $ficha = $this->crearFicha(['password' => 'Legacy-251-g']);
        $this->crearCuenta($ficha->usuario, $ficha->client_id, 'De-users-251-g');

        $this->entrar($ficha, 'De-users-251-g')->assertOk();

        $this->assertNull($this->hash($ficha));
    }

    public function test_cuenta_ajena_con_el_mismo_usuario_no_entra(): void
    {
        $ajena = $this->crearFicha(['password' => 'De-la-otra-251-h']);
        $ficha = $this->crearFicha(['password' => 'Legacy-251-h']);
        $cuentaId = $this->crearCuenta($ficha->usuario, $ajena->client_id, 'De-la-otra-251-h');

        $this->entrar($ficha, 'Legacy-251-h')->assertStatus(401);
        $this->entrar($ficha, 'De-la-otra-251-h')->assertStatus(401);

        $this->assertNull($this->hash($ficha));
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $cuentaId)->count());
    }

    public function test_un_fallo_al_guardar_el_hash_no_tumba_el_login(): void
    {
        $ficha = $this->crearFicha(['password' => 'Legacy-251-i']);
        $this->crearCuenta($ficha->usuario, $ficha->client_id, 'Otra-de-users-251');

        DB::connection()->beforeExecuting(function (string $sql) {
            if (str_starts_with($sql, 'update') && str_contains($sql, 'web_password_hash')) {
                throw new \RuntimeException('Fallo simulado al guardar el hash');
            }
        });

        $this->entrar($ficha, 'Legacy-251-i')->assertOk()->assertJsonPath('success', true);
        $this->assertNull($this->hash($ficha));
    }
}
