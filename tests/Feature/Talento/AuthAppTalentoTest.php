<?php

namespace Tests\Feature\Talento;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Services\Security\PasswordService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\CreatesApplication;

/**
 * #693: sesión de la app de Talento (token con vencimiento propio, límite de intentos de login,
 * baja del colaborador). Usuarios sintéticos dentro de una transacción que se revierte.
 */
class AuthAppTalentoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const PASS = 'Prueba-693-segura';

    private function usuario(string $status = 'active', bool $conColaborador = true): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => 'Talento693', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint693_' . Str::random(8), 'password' => PasswordService::make(self::PASS),
            'estado' => 'activo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($conColaborador) {
            DB::table('talento_colaboradores')->insert([
                'user_id' => $id, 'type' => 'interno', 'status' => $status,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return User::find($id);
    }

    private function login(User $u, string $pass = self::PASS)
    {
        return $this->postJson('/talento/api/auth/login', ['usuario' => $u->login_user, 'password' => $pass]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['talento.mobile.login_max_attempts' => 5, 'talento.mobile.token_ttl_minutes' => 60 * 24 * 30]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_login_correcto_emite_token_con_vencimiento(): void
    {
        $u = $this->usuario();
        $r = $this->login($u)->assertOk()->assertJsonStructure(['token']);

        $t = PersonalAccessToken::where('tokenable_id', $u->id)->where('name', 'talento-mobile')->first();
        $this->assertNotNull($t->expires_at);
        $this->assertTrue($t->expires_at->between(now()->addDays(29), now()->addDays(31)));
        $this->withToken($r->json('token'))->getJson('/talento/api/me')->assertOk();
    }

    public function test_intentos_fallidos_llegan_al_429_con_el_mismo_mensaje_exista_o_no_el_usuario(): void
    {
        $u = $this->usuario();
        $fantasma = new User(['login_user' => 'no_existe_693_' . Str::random(6)]);
        foreach ([$u, $fantasma] as $x) {
            for ($i = 0; $i < 5; $i++) {
                $this->login($x, 'mala')->assertStatus(401);
            }
        }
        $a = $this->login($u, 'mala')->assertStatus(429);
        $b = $this->login($fantasma, 'mala')->assertStatus(429);
        $this->assertSame($a->json('message'), $b->json('message'));
        $this->assertNotEmpty($a->headers->get('Retry-After'));
    }

    public function test_contrasena_correcta_bajo_bloqueo_tambien_da_429(): void
    {
        $u = $this->usuario();
        for ($i = 0; $i < 5; $i++) {
            $this->login($u, 'mala')->assertStatus(401);
        }
        $this->login($u)->assertStatus(429);
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $u->id)->where('name', 'talento-mobile')->count());
    }

    public function test_login_exitoso_limpia_el_contador(): void
    {
        $u = $this->usuario();
        for ($i = 0; $i < 4; $i++) {
            $this->login($u, 'mala')->assertStatus(401);
        }
        $this->login($u)->assertOk();
        for ($i = 0; $i < 4; $i++) {
            $this->login($u, 'mala')->assertStatus(401);
        }
    }

    public function test_token_vencido_devuelve_401(): void
    {
        $u = $this->usuario();
        $token = $this->login($u)->assertOk()->json('token');
        PersonalAccessToken::where('tokenable_id', $u->id)->update(['expires_at' => now()->subMinute()]);
        $this->withToken($token)->getJson('/talento/api/me')->assertStatus(401);
    }

    public function test_logout_revoca_el_token(): void
    {
        $u = $this->usuario();
        $token = $this->login($u)->assertOk()->json('token');
        $this->withToken($token)->postJson('/talento/api/auth/logout')->assertOk();
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $u->id)->where('name', 'talento-mobile')->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/talento/api/me')->assertStatus(401);
    }

    public function test_colaborador_desactivado_pierde_tokens_y_no_entra(): void
    {
        $u = $this->usuario();
        $this->login($u)->assertOk();
        $otro = $u->createToken('otra-app')->plainTextToken;
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $u->id)->where('name', 'talento-mobile')->count());

        TalentoColaborador::where('user_id', $u->id)->firstOrFail()->update(['status' => 'inactive']);

        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $u->id)->where('name', 'talento-mobile')->count());
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $u->id)->where('name', 'otra-app')->count());
        $this->login($u)->assertStatus(403);
        $this->assertNotEmpty($otro);
    }

    public function test_token_de_megafamilia_no_cambia(): void
    {
        $u = $this->usuario('active', false);
        $mf = $u->createToken('megafamilia-mobile')->accessToken;
        $this->assertNull($mf->expires_at);

        // Un login de Talento del mismo usuario (sin colaborador => 403) no toca el token ajeno.
        $this->login($u)->assertStatus(403);
        $this->assertNull(PersonalAccessToken::find($mf->id)->expires_at);
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $u->id)->where('name', 'megafamilia-mobile')->count());
        $this->assertNull(config('sanctum.expiration'));
    }
}
