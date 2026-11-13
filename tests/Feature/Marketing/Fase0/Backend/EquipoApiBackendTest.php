<?php

namespace Tests\Feature\Marketing\Fase0\Backend;

use App\Models\User;
use App\Modules\Addons\Marketing\Models\Empresa;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Marketing\Fase0\Backend\Concerns\EscenarioBackend;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 0 · backend — rutas 7 a 10: equipo de una empresa.
 */
class EquipoApiBackendTest extends MarketingTestCase
{
    use EscenarioBackend;

    private User $admin;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPermisos();
        $this->admin = $this->crearUsuario(['super-administrator'], ['name' => 'Zoe', 'father_last_name' => 'Admin']);
        $this->admin->givePermissionTo('marketing.empresas.gestionar');
        $this->asignar($this->admin, 1, 'admin');

        $this->empresa = $this->crearEmpresa();
        $this->asignar($this->admin, $this->empresa->id, 'admin');
    }

    private function url(string $resto = ''): string
    {
        return "/api/marketing/empresas/{$this->empresa->id}/usuarios{$resto}";
    }

    // ── 7) index ──────────────────────────────────────────────────────────────

    public function test_index_lista_activos_primero_y_luego_por_nombre(): void
    {
        $ana = $this->crearUsuario(['Vendedor'], ['name' => 'Ana', 'father_last_name' => 'López']);
        $beto = $this->crearUsuario(['Vendedor'], ['name' => 'Beto', 'father_last_name' => 'Ruiz']);
        $carla = $this->crearUsuario(['Vendedor'], ['name' => 'Carla', 'father_last_name' => 'Díaz']);
        $this->asignar($beto, $this->empresa->id, 'ventas');
        $this->asignar($ana, $this->empresa->id, 'marketing', false);
        $this->asignar($carla, $this->empresa->id, 'ventas');

        $r = $this->actingAs($this->admin)->getJson($this->url())->assertOk()->assertJsonPath('ok', true);

        $this->assertSame([$beto->id, $carla->id, $this->admin->id, $ana->id], array_column($r->json('data'), 'user_id'));
        $miembro = $r->json('data.0');
        $this->assertSame(['user_id', 'nombre', 'login_user', 'email', 'rol', 'activo', 'asignado_at'], array_keys($miembro));
        $this->assertSame('Beto Ruiz', $miembro['nombre']);
        $this->assertFalse($r->json('data.3.activo'));
    }

    public function test_index_de_otra_empresa_es_404(): void
    {
        $ajena = $this->crearEmpresa();
        $this->actingAs($this->admin)->getJson("/api/marketing/empresas/{$ajena->id}/usuarios")->assertStatus(404);
        $this->actingAs($this->admin)->getJson('/api/marketing/empresas/999999/usuarios')->assertStatus(404);
    }

    // ── 8) store ──────────────────────────────────────────────────────────────

    public function test_store_agrega_con_201_y_no_toca_roles_spatie(): void
    {
        $diana = $this->crearUsuario(['Mostrador']);
        $rolesAntes = DB::table('model_has_roles')->where('model_id', $diana->id)->pluck('role_id')->sort()->values()->all();
        $permisosAntes = DB::table('model_has_permissions')->where('model_id', $diana->id)->count();

        $this->actingAs($this->admin)->postJson($this->url(), ['user_id' => $diana->id, 'rol' => 'ventas'])
            ->assertStatus(201)
            ->assertJsonPath('data.user_id', $diana->id)
            ->assertJsonPath('data.rol', 'ventas')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('marketing_empresa_usuario', ['company_id' => $this->empresa->id, 'user_id' => $diana->id, 'rol' => 'ventas', 'created_by' => $this->admin->id]);
        $this->assertSame($rolesAntes, DB::table('model_has_roles')->where('model_id', $diana->id)->pluck('role_id')->sort()->values()->all());
        $this->assertSame($permisosAntes, DB::table('model_has_permissions')->where('model_id', $diana->id)->count());
    }

    public function test_store_ya_asignado_y_doble_envio(): void
    {
        $diana = $this->crearUsuario(['Mostrador']);
        $this->actingAs($this->admin)->postJson($this->url(), ['user_id' => $diana->id, 'rol' => 'ventas'])->assertStatus(201);

        $this->actingAs($this->admin)->postJson($this->url(), ['user_id' => $diana->id, 'rol' => 'admin'])
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'error' => 'ya_asignado', 'mensaje' => 'Esa persona ya está en esta empresa.']);

        $this->assertSame(1, DB::table('marketing_empresa_usuario')->where('company_id', $this->empresa->id)->where('user_id', $diana->id)->count());
        $this->assertDatabaseHas('marketing_empresa_usuario', ['company_id' => $this->empresa->id, 'user_id' => $diana->id, 'rol' => 'ventas']);
    }

    public function test_store_reactiva_con_200_y_el_rol_nuevo(): void
    {
        $diana = $this->crearUsuario(['Mostrador']);
        $this->asignar($diana, $this->empresa->id, 'ventas', false);

        $this->actingAs($this->admin)->postJson($this->url(), ['user_id' => $diana->id, 'rol' => 'marketing'])
            ->assertStatus(200)->assertJsonPath('data.rol', 'marketing')->assertJsonPath('data.activo', true);
    }

    public function test_store_valida_persona_y_rol(): void
    {
        $inactivo = $this->crearUsuario(['Mostrador'], ['estado' => 'inactivo']);
        $bot = $this->crearUsuario(['Mostrador'], ['is_system' => 1]);
        $soloCliente = $this->crearUsuario(['client']);
        $sinRoles = $this->crearUsuario([]);
        $bueno = $this->crearUsuario(['Mostrador']);

        foreach ([$inactivo->id, $bot->id, $soloCliente->id, $sinRoles->id, 999999999, 'abc', null, [1], -1] as $userId) {
            $this->actingAs($this->admin)->postJson($this->url(), ['user_id' => $userId, 'rol' => 'ventas'])
                ->assertStatus(422)->assertJsonValidationErrors('user_id');
        }

        foreach (['superjefe', '', null, ['admin'], 'ADMIN'] as $rol) {
            $this->actingAs($this->admin)->postJson($this->url(), ['user_id' => $bueno->id, 'rol' => $rol])
                ->assertStatus(422)->assertJsonValidationErrors('rol');
        }

        $this->assertDatabaseMissing('marketing_empresa_usuario', ['company_id' => $this->empresa->id, 'user_id' => $bueno->id]);
    }

    public function test_store_en_empresa_ajena_es_404_aun_con_cuerpo_invalido(): void
    {
        $ajena = $this->crearEmpresa();
        $diana = $this->crearUsuario(['Mostrador']);

        $this->actingAs($this->admin)->postJson("/api/marketing/empresas/{$ajena->id}/usuarios", ['user_id' => $diana->id, 'rol' => 'admin'])->assertStatus(404);
        $this->actingAs($this->admin)->postJson("/api/marketing/empresas/{$ajena->id}/usuarios", [])->assertStatus(404);
        $this->assertDatabaseMissing('marketing_empresa_usuario', ['company_id' => $ajena->id, 'user_id' => $diana->id]);
    }

    // ── 9) update ─────────────────────────────────────────────────────────────

    public function test_update_cambia_rol_y_activo(): void
    {
        $diana = $this->crearUsuario(['Mostrador']);
        $this->asignar($diana, $this->empresa->id, 'ventas');

        $this->actingAs($this->admin)->putJson($this->url("/{$diana->id}"), ['rol' => 'marketing'])
            ->assertOk()->assertJsonPath('data.rol', 'marketing')->assertJsonPath('data.activo', true);

        $this->actingAs($this->admin)->putJson($this->url("/{$diana->id}"), ['activo' => false])
            ->assertOk()->assertJsonPath('data.activo', false)->assertJsonPath('data.rol', 'marketing');

        $this->assertDatabaseHas('marketing_empresa_usuario', ['company_id' => $this->empresa->id, 'user_id' => $diana->id, 'activo' => 0, 'updated_by' => $this->admin->id]);
    }

    public function test_update_no_deja_la_empresa_sin_admin_activo_ni_siquiera_a_uno_mismo(): void
    {
        $esperado = ['ok' => false, 'error' => 'ultimo_admin', 'mensaje' => 'La empresa debe conservar al menos un administrador activo.'];

        $this->actingAs($this->admin)->putJson($this->url("/{$this->admin->id}"), ['rol' => 'ventas'])->assertStatus(422)->assertExactJson($esperado);
        $this->actingAs($this->admin)->putJson($this->url("/{$this->admin->id}"), ['activo' => false])->assertStatus(422)->assertExactJson($esperado);
        $this->actingAs($this->admin)->putJson($this->url("/{$this->admin->id}"), ['rol' => 'admin', 'activo' => false])->assertStatus(422);

        // Con otro admin activo, sí se puede.
        $otro = $this->crearUsuario(['Mostrador']);
        $this->asignar($otro, $this->empresa->id, 'admin');
        $this->actingAs($this->admin)->putJson($this->url("/{$this->admin->id}"), ['rol' => 'marketing'])->assertOk();

        // …y ahora el otro es el último.
        $this->actingAs($otro)->withSession(['marketing.empresa_activa_id' => $this->empresa->id]);
        $otro->givePermissionTo('marketing.empresas.gestionar');
        $this->actingAs($otro)->putJson($this->url("/{$otro->id}"), ['activo' => false])->assertStatus(422)->assertJsonPath('error', 'ultimo_admin');
    }

    public function test_update_valida_cuerpo(): void
    {
        $diana = $this->crearUsuario(['Mostrador']);
        $this->asignar($diana, $this->empresa->id, 'ventas');

        foreach ([[], ['rol' => 'jefe'], ['activo' => 'quizas'], ['rol' => null, 'activo' => null], ['rol' => ['admin']]] as $cuerpo) {
            $this->actingAs($this->admin)->putJson($this->url("/{$diana->id}"), $cuerpo)->assertStatus(422);
        }
    }

    public function test_update_sin_fila_o_empresa_ajena_es_404(): void
    {
        $sinFila = $this->crearUsuario(['Mostrador']);
        $ajena = $this->crearEmpresa();
        $deAjena = $this->crearUsuario(['Mostrador']);
        $this->asignar($deAjena, $ajena->id, 'admin');

        $this->actingAs($this->admin)->putJson($this->url("/{$sinFila->id}"), ['rol' => 'ventas'])->assertStatus(404);
        $this->actingAs($this->admin)->putJson($this->url('/abc'), ['rol' => 'ventas'])->assertStatus(404);
        $this->actingAs($this->admin)->putJson("/api/marketing/empresas/{$ajena->id}/usuarios/{$deAjena->id}", ['activo' => false])->assertStatus(404);

        $this->assertDatabaseHas('marketing_empresa_usuario', ['company_id' => $ajena->id, 'user_id' => $deAjena->id, 'activo' => 1]);
    }

    // ── 10) disponibles ──────────────────────────────────────────────────────

    public function test_disponibles_busca_y_excluye_lo_que_debe(): void
    {
        $token = 'zqx' . substr(uniqid(), -5);
        $libre = $this->crearUsuario(['Mostrador'], ['name' => "Diana {$token}", 'father_last_name' => 'López', 'email' => "{$token}@ejemplo.mx"]);
        $miembro = $this->crearUsuario(['Mostrador'], ['name' => "Miembro {$token}"]);
        $exMiembro = $this->crearUsuario(['Mostrador'], ['name' => "Exmiembro {$token}"]);
        $inactivo = $this->crearUsuario(['Mostrador'], ['name' => "Inactivo {$token}", 'estado' => 'inactivo']);
        $bot = $this->crearUsuario(['Mostrador'], ['name' => "Bot {$token}", 'is_system' => 1]);
        $cliente = $this->crearUsuario(['client'], ['name' => "Cliente {$token}"]);
        $this->asignar($miembro, $this->empresa->id, 'ventas');
        $this->asignar($exMiembro, $this->empresa->id, 'ventas', false);

        $r = $this->actingAs($this->admin)->getJson($this->url("-disponibles?buscar={$token}"))->assertOk();
        $ids = array_column($r->json('data'), 'id');
        sort($ids);
        $esperados = [$libre->id, $exMiembro->id];
        sort($esperados);

        $this->assertSame($esperados, $ids);
        $fila = collect($r->json('data'))->firstWhere('id', $libre->id);
        $this->assertSame(['id', 'nombre', 'login_user', 'email'], array_keys($fila));
        $this->assertSame("Diana {$token} López", $fila['nombre']);

        // Por apellido, login y correo también.
        $this->assertContains($libre->id, array_column($this->actingAs($this->admin)->getJson($this->url('-disponibles?buscar=' . urlencode($libre->login_user)))->json('data'), 'id'));
        $this->assertContains($libre->id, array_column($this->actingAs($this->admin)->getJson($this->url("-disponibles?buscar={$token}@ejemplo"))->json('data'), 'id'));
    }

    public function test_disponibles_trata_comodines_e_inyeccion_como_texto(): void
    {
        $this->crearUsuario(['Mostrador']);

        foreach (['%%', '__', '\\%'] as $buscar) {
            $this->actingAs($this->admin)->getJson($this->url('-disponibles?buscar=' . urlencode($buscar)))
                ->assertOk()->assertJsonCount(0, 'data');
        }

        // Inyección: o la frena el WAF global (403) o se busca como texto literal (0 resultados).
        foreach (["' OR 1=1 --", '%" OR "1"="1'] as $buscar) {
            $r = $this->actingAs($this->admin)->getJson($this->url('-disponibles?buscar=' . urlencode($buscar)));
            $this->assertContains($r->status(), [200, 403]);
            if ($r->status() === 200) {
                $this->assertSame([], $r->json('data'));
            }
        }
    }

    public function test_disponibles_maximo_20(): void
    {
        $token = 'mx' . substr(uniqid(), -6);
        for ($i = 0; $i < 22; $i++) {
            $this->crearUsuario(['Mostrador'], ['name' => "Masivo {$token} {$i}"]);
        }

        $this->actingAs($this->admin)->getJson($this->url("-disponibles?buscar={$token}"))->assertOk()->assertJsonCount(20, 'data');
    }

    public function test_disponibles_valida_buscar_y_empresa(): void
    {
        foreach (['', '?buscar=', '?buscar=a', '?buscar=' . str_repeat('a', 61), '?buscar[]=ab'] as $q) {
            $this->actingAs($this->admin)->getJson($this->url('-disponibles' . $q))->assertStatus(422)->assertJsonValidationErrors('buscar');
        }

        $ajena = $this->crearEmpresa();
        $this->actingAs($this->admin)->getJson("/api/marketing/empresas/{$ajena->id}/usuarios-disponibles?buscar=dia")->assertStatus(404);
    }
}
