<?php

namespace Tests\Feature\Unificacion;

use App\Models\User;
use App\Services\Security\PasswordService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\CreatesApplication;

/**
 * UNI-T O2 (#914): fija la FORMA de respuesta de la API móvil de Talento (/talento/api/*) para
 * correrla antes y después de cada cambio de la unificación. Solo observa: no cambia comportamiento.
 * Solo contra megaisp_test; usuarios sintéticos (UNI-PRUEBA) dentro de una transacción que se revierte.
 */
class ContratoApiTalentoMobileTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const PASS = 'UNI-PRUEBA-914-segura';

    private function usuario(): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'UNI-PRUEBA', 'father_last_name' => 'Contrato914', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'uni914_' . Str::random(8), 'password' => PasswordService::make(self::PASS),
            'estado' => 'activo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('talento_colaboradores')->insert([
            'user_id' => $id, 'type' => 'interno', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::find($id);
    }

    private function token(User $u): string
    {
        return $this->postJson('/talento/api/auth/login', ['usuario' => $u->login_user, 'password' => self::PASS])
            ->assertOk()->json('token');
    }

    public function test_rutas_del_contrato_siguen_registradas(): void
    {
        $esperadas = [
            'POST talento/api/auth/login', 'POST talento/api/auth/logout', 'GET talento/api/me',
            'GET talento/api/health', 'GET talento/api/app/latest', 'GET talento/api/app/branding',
            'GET talento/api/asistencia/hoy', 'POST talento/api/asistencia/checkin', 'POST talento/api/asistencia/checkout',
            'GET talento/api/cambios', 'GET talento/api/ots/hoy', 'GET talento/api/ots/historial', 'GET talento/api/ots/{id}',
            'POST talento/api/ots/{id}/evidencia', 'POST talento/api/ots/{id}/iniciar', 'POST talento/api/ots/{id}/completar',
            'POST talento/api/ots/{id}/incidencia', 'PUT talento/api/ots/{id}/nota', 'GET talento/api/compensacion/semana',
            'GET talento/api/portal/equipo', 'GET talento/api/portal/compensacion', 'GET talento/api/portal/liquidaciones',
            'GET talento/api/portal/documentos', 'GET talento/api/portal/custodia',
        ];
        $reales = [];
        foreach (Route::getRoutes() as $r) {
            foreach ($r->methods() as $m) {
                $reales[$m . ' ' . $r->uri()] = true;
            }
        }
        foreach ($esperadas as $e) {
            $this->assertArrayHasKey($e, $reales, "Falta la ruta de contrato: $e");
        }
    }

    public function test_publicas_health_branding_y_latest(): void
    {
        $this->getJson('/talento/api/health')->assertOk()
            ->assertJsonStructure(['ok', 'app', 'tenant_name', 'tenant_logo_url'])->assertJsonPath('ok', true);
        $this->getJson('/talento/api/app/branding')->assertOk()->assertJsonStructure(['company_name', 'logo_url']);
        $this->getJson('/talento/api/app/latest?version_code=999999999')->assertOk()
            ->assertExactJson(['update_available' => false]);
    }

    public function test_login_forma_de_error_es_message(): void
    {
        $this->postJson('/talento/api/auth/login', [])->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        $u = $this->usuario();
        $this->postJson('/talento/api/auth/login', ['usuario' => $u->login_user, 'password' => 'mala'])
            ->assertStatus(401)->assertExactJson(['message' => 'Credenciales incorrectas.']);
    }

    public function test_sin_token_las_protegidas_responden_401(): void
    {
        foreach (['/talento/api/me', '/talento/api/ots/hoy', '/talento/api/asistencia/hoy', '/talento/api/portal/equipo'] as $url) {
            $this->getJson($url)->assertStatus(401);
        }
    }

    public function test_con_token_me_asistencia_y_ots_hoy_tienen_su_forma(): void
    {
        $u = $this->usuario();
        $t = $this->token($u);

        $this->withToken($t)->getJson('/talento/api/me')->assertOk()->assertJsonStructure([
            'id', 'nombre', 'tipo', 'track', 'es_tecnico', 'es_vendedor', 'es_mostrador', 'tiene_equipo', 'email', 'telefono',
        ]);
        $this->withToken($t)->getJson('/talento/api/asistencia/hoy')->assertOk()
            ->assertExactJson(['check_in_at' => null, 'check_out_at' => null]);
        $this->withToken($t)->getJson('/talento/api/ots/hoy')->assertOk()->assertJsonStructure(['ots']);
        $this->withToken($t)->getJson('/talento/api/ots/99999999')->assertStatus(404);
    }
}
