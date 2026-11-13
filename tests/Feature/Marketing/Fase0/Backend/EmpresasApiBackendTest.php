<?php

namespace Tests\Feature\Marketing\Fase0\Backend;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Marketing\Fase0\Backend\Concerns\EscenarioBackend;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 0 · backend — rutas 3 a 6: vista y API de empresas.
 */
class EmpresasApiBackendTest extends MarketingTestCase
{
    use EscenarioBackend;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPermisos();
        $this->admin = $this->crearUsuario(['super-administrator']);
        $this->admin->givePermissionTo('marketing.empresas.gestionar');
        $this->asignar($this->admin, 1, 'admin');
    }

    private function cuerpo(array $extra = []): array
    {
        return array_merge(['nombre' => 'Meganet Empresarial ' . uniqid(), 'color_primario' => '#1F3A93', 'color_secundario' => null], $extra);
    }

    // ── 3) vista ──────────────────────────────────────────────────────────────

    public function test_vista_responde_al_admin_con_el_tag_de_la_pantalla(): void
    {
        $this->usarLayoutMinimo();

        $this->actingAs($this->admin)->get('/marketing/empresas')
            ->assertOk()
            ->assertSee('<marketing-empresas-equipo :auth-user-id="' . $this->admin->id . '">', false);
    }

    public function test_vista_y_api_exigen_el_permiso_spatie_ademas_del_rol(): void
    {
        $sinPermiso = $this->crearUsuario(['Vendedor']);
        $this->asignar($sinPermiso, 1, 'admin');

        $this->actingAs($sinPermiso)->getJson('/api/marketing/empresas')->assertStatus(403);
        $this->actingAs($sinPermiso)->get('/marketing/empresas')->assertStatus(403);
    }

    // ── 4) index ──────────────────────────────────────────────────────────────

    public function test_index_lista_solo_empresas_donde_es_admin_activo_con_conteos(): void
    {
        $otra = $this->crearEmpresa();           // no es miembro
        $comoVentas = $this->crearEmpresa();     // es ventas
        $deBaja = $this->crearEmpresa();         // fue admin, dado de baja
        $inactiva = $this->crearEmpresa(['activo' => false]); // admin de una empresa inactiva: sí la ve
        $this->asignar($this->admin, $comoVentas->id, 'ventas');
        $this->asignar($this->admin, $deBaja->id, 'admin', false);
        $this->asignar($this->admin, $inactiva->id, 'admin');

        $extra = $this->crearUsuario();
        $this->asignar($extra, 1, 'ventas');
        $exMiembro = $this->crearUsuario();
        $this->asignar($exMiembro, 1, 'admin', false);

        $r = $this->actingAs($this->admin)->getJson('/api/marketing/empresas')->assertOk()->assertJsonPath('ok', true);
        $ids = array_column($r->json('data'), 'id');

        $this->assertContains(1, $ids);
        $this->assertContains($inactiva->id, $ids);
        $this->assertNotContains($otra->id, $ids);
        $this->assertNotContains($comoVentas->id, $ids);
        $this->assertNotContains($deBaja->id, $ids);
        $this->assertSame($ids, collect($ids)->sort()->values()->all(), 'orden por id');

        $meganet = collect($r->json('data'))->firstWhere('id', 1);
        $esperadoMiembros = DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('activo', 1)->count();
        $esperadoAdmins = DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('activo', 1)->where('rol', 'admin')->count();
        $this->assertSame($esperadoMiembros, $meganet['miembros']);
        $this->assertSame($esperadoAdmins, $meganet['admins']);
        $this->assertTrue($meganet['es_principal']);
        $this->assertTrue($meganet['activo']);
        $this->assertSame(['id', 'nombre', 'slug', 'color_primario', 'color_secundario', 'activo', 'es_principal', 'miembros', 'admins', 'created_at'], array_keys($meganet));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-0[56]:00$/', $meganet['created_at']);
    }

    // ── 5) store ──────────────────────────────────────────────────────────────

    public function test_store_crea_la_empresa_y_deja_al_creador_como_admin(): void
    {
        $r = $this->actingAs($this->admin)->postJson('/api/marketing/empresas', [
            'nombre' => 'Meganet Empresarial', 'slug' => 'meganet-empresarial', 'color_primario' => '#1F3A93', 'color_secundario' => null,
        ])->assertStatus(201);

        $r->assertJson(['ok' => true, 'data' => [
            'nombre' => 'Meganet Empresarial', 'slug' => 'meganet-empresarial', 'color_primario' => '#1F3A93',
            'color_secundario' => null, 'activo' => true, 'es_principal' => false, 'miembros' => 1, 'admins' => 1,
        ]]);

        $id = $r->json('data.id');
        $this->assertDatabaseHas('marketing_empresas', ['id' => $id, 'created_by' => $this->admin->id, 'activo' => 1]);
        $this->assertDatabaseHas('marketing_empresa_usuario', [
            'company_id' => $id, 'user_id' => $this->admin->id, 'rol' => 'admin', 'activo' => 1, 'created_by' => $this->admin->id,
        ]);
    }

    public function test_store_deriva_el_slug_del_nombre_con_acentos(): void
    {
        $this->actingAs($this->admin)->postJson('/api/marketing/empresas', ['nombre' => '  Ñandú Telecomunicaciones Peñón  '])
            ->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Ñandú Telecomunicaciones Peñón')
            ->assertJsonPath('data.slug', 'nandu-telecomunicaciones-penon');
    }

    public function test_store_acepta_emojis_y_guarda_texto_tal_cual(): void
    {
        $nombre = 'Marca 🚀 <script>alert(1)</script>';
        $this->actingAs($this->admin)->postJson('/api/marketing/empresas', ['nombre' => $nombre, 'slug' => 'marca-cohete'])
            ->assertStatus(201)->assertJsonPath('data.nombre', $nombre);
        $this->assertDatabaseHas('marketing_empresas', ['slug' => 'marca-cohete', 'nombre' => $nombre]);
    }

    public function test_store_ignora_activo_al_crear(): void
    {
        $this->actingAs($this->admin)->postJson('/api/marketing/empresas', $this->cuerpo(['slug' => 'nace-activa', 'activo' => false]))
            ->assertStatus(201)->assertJsonPath('data.activo', true);
    }

    public function test_store_valida_campos(): void
    {
        $casos = [
            'nombre' => [[], ['nombre' => ''], ['nombre' => 'A'], ['nombre' => str_repeat('x', 121)], ['nombre' => ['a', 'b']], ['nombre' => str_repeat('x', 10000)]],
            'slug' => [
                ['nombre' => 'Bien', 'slug' => 'Con Mayusculas'], ['nombre' => 'Bien', 'slug' => 'a'],
                ['nombre' => 'Bien', 'slug' => str_repeat('a', 61)], ['nombre' => 'Bien', 'slug' => '-guion'],
                ['nombre' => 'Bien', 'slug' => 'doble--guion'],
                ['nombre' => '🚀🚀'], // slug derivado vacío
            ],
            'color_primario' => [['nombre' => 'Bien', 'slug' => 'bien', 'color_primario' => 'azul'], ['nombre' => 'Bien', 'slug' => 'bien', 'color_primario' => '#12345'], ['nombre' => 'Bien', 'slug' => 'bien', 'color_primario' => '#GGGGGG']],
            'color_secundario' => [['nombre' => 'Bien', 'slug' => 'bien', 'color_secundario' => '1F3A93']],
        ];

        foreach ($casos as $campo => $cuerpos) {
            foreach ($cuerpos as $cuerpo) {
                $this->actingAs($this->admin)->postJson('/api/marketing/empresas', $cuerpo)
                    ->assertStatus(422)->assertJsonValidationErrors($campo);
            }
        }

        // Inyección en el slug: la frena el WAF global (403) o la validación (422).
        $r = $this->actingAs($this->admin)->postJson('/api/marketing/empresas', ['nombre' => 'Bien', 'slug' => "x'; DROP TABLE users;--"]);
        $this->assertContains($r->status(), [403, 422]);

        $this->assertDatabaseMissing('marketing_empresas', ['slug' => 'bien']);
        $this->assertDatabaseMissing('marketing_empresas', ['nombre' => 'Bien']);
    }

    public function test_store_rechaza_nombre_y_slug_duplicados(): void
    {
        $this->actingAs($this->admin)->postJson('/api/marketing/empresas', ['nombre' => 'MEGANET'])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');

        $this->actingAs($this->admin)->postJson('/api/marketing/empresas', ['nombre' => 'Otra marca', 'slug' => 'meganet'])
            ->assertStatus(422)->assertJsonValidationErrors('slug');

        $this->assertSame('Ya existe una empresa con ese nombre.', $this->actingAs($this->admin)
            ->postJson('/api/marketing/empresas', ['nombre' => 'meganet'])->json('errors.nombre.0'));
    }

    public function test_store_doble_envio_no_duplica(): void
    {
        $cuerpo = ['nombre' => 'Marca Doble', 'slug' => 'marca-doble'];
        $this->actingAs($this->admin)->postJson('/api/marketing/empresas', $cuerpo)->assertStatus(201);
        $this->actingAs($this->admin)->postJson('/api/marketing/empresas', $cuerpo)->assertStatus(422);

        $this->assertSame(1, DB::table('marketing_empresas')->where('slug', 'marca-doble')->count());
    }

    // ── 6) update ─────────────────────────────────────────────────────────────

    public function test_update_cambia_un_subconjunto_de_campos(): void
    {
        $b = $this->crearEmpresa();
        $this->asignar($this->admin, $b->id, 'admin');

        $this->actingAs($this->admin)->putJson("/api/marketing/empresas/{$b->id}", ['color_secundario' => '#ABCDEF', 'activo' => false])
            ->assertOk()
            ->assertJsonPath('data.color_secundario', '#ABCDEF')
            ->assertJsonPath('data.activo', false)
            ->assertJsonPath('data.nombre', $b->nombre);

        $this->assertDatabaseHas('marketing_empresas', ['id' => $b->id, 'activo' => 0, 'updated_by' => $this->admin->id]);

        // Su propio nombre/slug no choca consigo mismo.
        $this->actingAs($this->admin)->putJson("/api/marketing/empresas/{$b->id}", ['nombre' => $b->nombre, 'slug' => $b->slug])->assertOk();
    }

    public function test_update_no_desactiva_la_principal(): void
    {
        $this->actingAs($this->admin)->putJson('/api/marketing/empresas/1', ['activo' => false])
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'error' => 'empresa_principal', 'mensaje' => 'Meganet es la empresa principal y no se puede desactivar.']);

        $this->assertDatabaseHas('marketing_empresas', ['id' => 1, 'activo' => 1]);

        // Otros cambios a la principal sí se permiten.
        $this->actingAs($this->admin)->putJson('/api/marketing/empresas/1', ['activo' => true, 'color_primario' => '#0066CC'])->assertOk();
    }

    public function test_update_valida_y_rechaza_duplicados(): void
    {
        $b = $this->crearEmpresa();
        $this->asignar($this->admin, $b->id, 'admin');

        $this->actingAs($this->admin)->putJson("/api/marketing/empresas/{$b->id}", ['slug' => 'meganet'])->assertStatus(422)->assertJsonValidationErrors('slug');
        $this->actingAs($this->admin)->putJson("/api/marketing/empresas/{$b->id}", ['nombre' => 'meganet'])->assertStatus(422)->assertJsonValidationErrors('nombre');
        $this->actingAs($this->admin)->putJson("/api/marketing/empresas/{$b->id}", ['nombre' => null])->assertStatus(422)->assertJsonValidationErrors('nombre');
        $this->actingAs($this->admin)->putJson("/api/marketing/empresas/{$b->id}", ['activo' => 'quizas'])->assertStatus(422)->assertJsonValidationErrors('activo');
    }

    public function test_update_de_empresa_ajena_o_inexistente_es_404_aun_con_cuerpo_invalido(): void
    {
        $ajena = $this->crearEmpresa();
        $comoVentas = $this->crearEmpresa();
        $this->asignar($this->admin, $comoVentas->id, 'ventas');

        foreach ([$ajena->id, $comoVentas->id, 999999, 'abc'] as $id) {
            $this->actingAs($this->admin)->putJson("/api/marketing/empresas/{$id}", ['nombre' => 'Robada'])->assertStatus(404);
            $this->actingAs($this->admin)->putJson("/api/marketing/empresas/{$id}", ['nombre' => ''])->assertStatus(404);
        }

        $this->assertDatabaseHas('marketing_empresas', ['id' => $ajena->id, 'nombre' => $ajena->nombre]);
    }
}
