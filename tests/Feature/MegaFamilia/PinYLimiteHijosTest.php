<?php

namespace Tests\Feature\MegaFamilia;

use App\Models\User;
use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Portal\PortalTestCase;

/**
 * #499: PIN del hijo y tope max_children. Usa tokens Sanctum reales porque el del hijo y el del
 * padre solo se distinguen por sus abilities.
 */
class PinYLimiteHijosTest extends PortalTestCase
{
    private const PIN = '482915';

    private function crearFamilia(int $maxChildren = 0): object
    {
        $cmi = $this->crearClientePortal();
        $user = User::find(DB::table('users')->insertGetId([
            'name'       => 'Prueba 499',
            'login_user' => 'mf499_' . uniqid(),
            'client_id'  => (string) $cmi->client_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $planId = DB::table('parental_plans')->insertGetId([
            'name'         => 'Plan 499',
            'slug'         => 'plan-499-' . uniqid(),
            'max_children' => $maxChildren,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $accountId = DB::table('parental_accounts')->insertGetId([
            'user_id'       => $user->id,
            'client_isp_id' => $cmi->client_id,
            'plan_id'       => $planId,
            'status'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return (object) [
            'user'       => $user,
            'account'    => $accountId,
            'client_isp' => (int) $cmi->client_id,
            'token'      => $user->createToken('padre')->plainTextToken,
        ];
    }

    private function crearPerfil(object $familia, array $campos = []): int
    {
        return DB::table('parental_profiles')->insertGetId($campos + [
            'account_id'    => $familia->account,
            'client_isp_id' => $familia->client_isp,
            'name'          => 'Hijo 499',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function crearDispositivo(object $familia, int $perfil, array $campos = []): int
    {
        return DB::table('parental_devices')->insertGetId($campos + [
            'profile_id'    => $perfil,
            'account_id'    => $familia->account,
            'client_isp_id' => $familia->client_isp,
            'name'          => 'Telefono 499',
            'linked_at'     => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function perfilConPin(object $familia): int
    {
        return $this->crearPerfil($familia, ['pin_hash' => Hash::make(self::PIN)]);
    }

    /** El guard de Sanctum recuerda al usuario entre peticiones de la misma prueba. */
    private function como(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function verificar(string $token, int $dispositivo, string $pin = self::PIN)
    {
        return $this->como($token)->postJson("/api/megafamilia/devices/{$dispositivo}/verify-pin", ['pin' => $pin]);
    }

    private function fijarPin(string $token, int $perfil, $pin = self::PIN)
    {
        return $this->como($token)->putJson("/api/megafamilia/profiles/{$perfil}/pin", ['pin' => $pin]);
    }

    private function alta(string $token)
    {
        return $this->como($token)->postJson('/api/megafamilia/profiles', ['name' => 'Nuevo 499']);
    }

    // ── max_children ─────────────────────────────────────────────────────

    public function test_plan_sin_limite_no_rechaza_altas(): void
    {
        $familia = $this->crearFamilia(0);
        foreach (range(1, 3) as $_) {
            $this->crearPerfil($familia);
        }

        $this->alta($familia->token)->assertStatus(201);
    }

    public function test_plan_con_limite_rechaza_al_llegar_y_no_cuenta_inactivos(): void
    {
        $familia = $this->crearFamilia(2);
        $this->crearPerfil($familia);
        $this->crearPerfil($familia, ['active' => false]);

        $this->alta($familia->token)->assertStatus(201);

        $this->alta($familia->token)->assertStatus(422)
            ->assertJsonPath('code', 'max_children_reached')
            ->assertJsonPath('max_children', 2);
        $this->assertSame(3, DB::table('parental_profiles')->where('account_id', $familia->account)->count());
    }

    // ── PIN: propiedad ───────────────────────────────────────────────────

    public function test_no_se_toca_el_pin_ni_el_dispositivo_de_otro_cliente(): void
    {
        $a = $this->crearFamilia();
        $b = $this->crearFamilia();
        $perfilB = $this->perfilConPin($b);
        $dispositivoB = $this->crearDispositivo($b, $perfilB);
        $hashB = DB::table('parental_profiles')->where('id', $perfilB)->value('pin_hash');

        $this->fijarPin($a->token, $perfilB, '111111')->assertStatus(404);
        $this->verificar($a->token, $dispositivoB)->assertStatus(404);
        $this->verificar($a->token, $dispositivoB, '000000')->assertStatus(404);

        $fila = DB::table('parental_profiles')->where('id', $perfilB)->first();
        $this->assertSame($hashB, $fila->pin_hash);
        $this->assertSame(0, (int) $fila->pin_attempts);

        $this->fijarPin($b->token, $perfilB)->assertOk();
        $this->verificar($b->token, $dispositivoB)->assertOk();
    }

    public function test_sin_sesion_no_hay_acceso(): void
    {
        $familia = $this->crearFamilia();
        $perfil = $this->perfilConPin($familia);
        $dispositivo = $this->crearDispositivo($familia, $perfil);

        $this->postJson("/api/megafamilia/devices/{$dispositivo}/verify-pin", ['pin' => self::PIN])->assertStatus(401);
        $this->putJson("/api/megafamilia/profiles/{$perfil}/pin", ['pin' => self::PIN])->assertStatus(401);
    }

    public function test_el_token_de_hijo_de_link_device_no_cambia_el_pin_y_solo_verifica_su_dispositivo(): void
    {
        $familia = $this->crearFamilia();
        $perfil = $this->perfilConPin($familia);
        $hermano = $this->crearDispositivo($familia, $this->perfilConPin($familia));

        $invitacion = $this->como($familia->token)->postJson("/api/megafamilia/profiles/{$perfil}/invite")->assertStatus(201);
        $vinculo = $this->postJson('/api/megafamilia/devices/link', ['token' => $invitacion->json('token')])->assertOk();
        $tokenHijo = $vinculo->json('token');
        $propio = $vinculo->json('device.id');

        $this->fijarPin($tokenHijo, $perfil, '111111')->assertStatus(403);
        $this->verificar($tokenHijo, $propio, '111111')->assertStatus(422);

        $this->verificar($tokenHijo, $hermano)->assertStatus(404);
        $this->verificar($tokenHijo, $propio)->assertOk();
    }

    // ── PIN: fijar ───────────────────────────────────────────────────────

    public function test_el_padre_fija_el_pin_con_hash_y_seis_digitos(): void
    {
        $familia = $this->crearFamilia();
        $perfil = $this->crearPerfil($familia);

        foreach (['12345', '1234567', 'abcdef'] as $invalido) {
            $this->fijarPin($familia->token, $perfil, $invalido)->assertStatus(422);
        }
        $this->fijarPin($familia->token, $perfil)->assertOk();

        $this->assertTrue(Hash::check(self::PIN, DB::table('parental_profiles')->where('id', $perfil)->value('pin_hash')));
    }

    public function test_el_hash_del_pin_no_se_serializa(): void
    {
        $familia = $this->crearFamilia();

        $this->assertArrayNotHasKey('pin_hash', ParentalProfile::find($this->perfilConPin($familia))->toArray());
    }

    // ── PIN: verificar ───────────────────────────────────────────────────

    public function test_pin_correcto_no_emite_credenciales_y_limpia_los_intentos(): void
    {
        $familia = $this->crearFamilia();
        $perfil = $this->perfilConPin($familia);
        $dispositivo = $this->crearDispositivo($familia, $perfil);

        $this->verificar($familia->token, $dispositivo, '000000')->assertStatus(422)->assertJsonPath('attempts_remaining', 2);
        $tokens = DB::table('personal_access_tokens')->where('tokenable_id', $familia->user->id)->count();

        $this->verificar($familia->token, $dispositivo)->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('token');

        $this->assertSame($tokens, DB::table('personal_access_tokens')->where('tokenable_id', $familia->user->id)->count());
        $this->assertSame(0, (int) DB::table('parental_profiles')->where('id', $perfil)->value('pin_attempts'));
    }

    public function test_tres_fallos_bloquean_hasta_que_el_padre_lo_restablece(): void
    {
        $familia = $this->crearFamilia();
        $perfil = $this->perfilConPin($familia);
        $dispositivo = $this->crearDispositivo($familia, $perfil);

        $this->verificar($familia->token, $dispositivo, '000000')->assertStatus(422)->assertJsonPath('attempts_remaining', 2);
        $this->verificar($familia->token, $dispositivo, '000000')->assertStatus(422)->assertJsonPath('attempts_remaining', 1);
        $this->verificar($familia->token, $dispositivo, '000000')->assertStatus(422)->assertJsonPath('attempts_remaining', 0);

        $this->verificar($familia->token, $dispositivo)->assertStatus(423)->assertJsonPath('code', 'pin_locked');
        $this->assertNotNull(DB::table('parental_profiles')->where('id', $perfil)->value('pin_locked_at'));

        $this->fijarPin($familia->token, $perfil)->assertOk();

        $fila = DB::table('parental_profiles')->where('id', $perfil)->first();
        $this->assertNull($fila->pin_locked_at);
        $this->assertSame(0, (int) $fila->pin_attempts);
        $this->verificar($familia->token, $dispositivo)->assertOk();
    }

    public function test_sin_pin_configurado_responde_pin_not_set(): void
    {
        $familia = $this->crearFamilia();
        $dispositivo = $this->crearDispositivo($familia, $this->crearPerfil($familia));

        $this->verificar($familia->token, $dispositivo)->assertStatus(422)->assertJsonPath('code', 'pin_not_set');
    }

    public function test_dispositivo_sin_vincular_o_desvinculado_no_verifica_ni_revive(): void
    {
        $familia = $this->crearFamilia();
        $perfil = $this->perfilConPin($familia);
        $sinVincular = $this->crearDispositivo($familia, $perfil, ['linked_at' => null]);
        $desvinculado = $this->crearDispositivo($familia, $perfil, ['status' => 'unlinked']);

        $this->verificar($familia->token, $sinVincular)->assertStatus(404);
        $this->verificar($familia->token, $desvinculado)->assertStatus(404);

        $this->assertSame('unlinked', DB::table('parental_devices')->where('id', $desvinculado)->value('status'));
    }

    public function test_verificar_lleva_throttle(): void
    {
        $familia = $this->crearFamilia();
        $dispositivo = $this->crearDispositivo($familia, $this->perfilConPin($familia));

        foreach (range(1, 10) as $_) {
            $this->verificar($familia->token, $dispositivo)->assertOk();
        }

        $this->verificar($familia->token, $dispositivo)->assertStatus(429);
    }
}
