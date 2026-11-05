<?php

namespace Tests\Feature\Talento;

use App\Models\User;
use App\Modules\Addons\Talento\Support\Actor;
use App\Services\Security\PasswordService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\CreatesApplication;

/** UNI-T B6 (#875): cuenta activa obligatoria en web (Actor) y API; inactivar revoca tokens. */
class CuentaActivaTalentoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const PASS = 'Prueba-875-segura';

    private function usuario(): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => 'Talento875', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint875_' . Str::random(8), 'password' => PasswordService::make(self::PASS),
            'estado' => 'activo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('talento_colaboradores')->insert([
            'user_id' => $id, 'type' => 'interno', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::find($id);
    }

    public function test_interruptor_apagado_no_cambia_nada(): void
    {
        config(['unificacion.interruptores.cuenta_activa' => false]);
        $u = $this->usuario();
        $token = $u->createToken('talento-mobile')->plainTextToken;
        DB::table('users')->where('id', $u->id)->update(['estado' => 'inactivo']);

        $this->withToken($token)->getJson('/talento/api/me')->assertOk();
        $this->assertNotNull(Actor::for(User::find($u->id))->talento());
    }

    public function test_cuenta_inactiva_no_entra_por_api_ni_actor_ni_login(): void
    {
        config(['unificacion.interruptores.cuenta_activa' => true]);
        $u = $this->usuario();
        $token = $u->createToken('talento-mobile')->plainTextToken;
        $this->withToken($token)->getJson('/talento/api/me')->assertOk();

        DB::table('users')->where('id', $u->id)->update(['estado' => 'inactivo']);
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/talento/api/me')->assertStatus(403);
        $this->assertNull(Actor::for(User::find($u->id))->talento());
        $this->postJson('/talento/api/auth/login', ['usuario' => $u->login_user, 'password' => self::PASS])->assertStatus(403);
    }

    public function test_inactivar_la_cuenta_revoca_sus_tokens(): void
    {
        config(['unificacion.interruptores.cuenta_activa' => true]);
        $u = $this->usuario();
        $u->createToken('talento-mobile');
        $u->estado = 'inactivo';
        $u->save();

        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $u->id)->count());
    }
}
