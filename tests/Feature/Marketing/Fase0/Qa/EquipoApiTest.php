<?php

namespace Tests\Feature\Marketing\Fase0\Qa;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Marketing\Fase0\Qa\Concerns\EscenarioEmpresas;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 0 — API de equipo, rutas nuevas 7–10 (§2.6) con ataque.
 *
 * Doble envío, último admin (incluido uno mismo), user_id de sistema/inactivo/solo client,
 * rol fuera de lista, admin de B pidiendo {id} de A → 404, `usuarios-disponibles` sin campos
 * sensibles e inyección SQL en `buscar`. La pantalla nunca toca roles/permisos Spatie (R10).
 */
class EquipoApiTest extends MarketingTestCase
{
    use EscenarioEmpresas;

    private const MSG_ULTIMO_ADMIN = 'La empresa debe conservar al menos un administrador activo.';
    private const MSG_YA_ASIGNADO = 'Esa persona ya está en esta empresa.';

    private User $candidato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenario(conDatos: false);
        // Persona de staff real sin empresa de Marketing: rol Vendedor (≠ client), activa, no de sistema.
        $this->candidato = $this->crearUsuario('qa_candidato', [], superAdmin: false, permisosMarketing: false, conGestionar: false, extra: ['name' => 'Diana QA Candidata']);
        $this->candidato->assignRole('Vendedor');
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function urlB(string $resto = ''): string
    {
        return "/api/marketing/empresas/{$this->empresaB->id}/usuarios{$resto}";
    }

    private function comoAdminB(): static
    {
        return $this->como($this->adminB, $this->empresaB->id);
    }

    private function spatieDe(User $u): array
    {
        return [
            DB::table('model_has_roles')->where('model_id', $u->id)->where('model_type', User::class)->pluck('role_id')->sort()->values()->all(),
            DB::table('model_has_permissions')->where('model_id', $u->id)->where('model_type', User::class)->pluck('permission_id')->sort()->values()->all(),
        ];
    }

    // ── GET usuarios ─────────────────────────────────────────────────────────

    public function test_index_lista_activos_primero_luego_por_nombre_con_forma_exacta(): void
    {
        $zeta = $this->crearUsuario('qa_zeta', [], extra: ['name' => 'Zeta QA']);
        $alfa = $this->crearUsuario('qa_alfa', [], extra: ['name' => 'Alfa QA']);
        $this->asignar($zeta, $this->empresaB->id, 'ventas');
        $this->asignar($alfa, $this->empresaB->id, 'marketing', activo: false);

        $r = $this->comoAdminB()->getJson($this->urlB());
        $r->assertOk()->assertJsonPath('ok', true);

        $data = $r->json('data');
        $this->assertCount(3, $data);
        $this->assertSame(['user_id', 'nombre', 'login_user', 'email', 'rol', 'activo', 'asignado_at'], array_keys($data[0]));

        $activos = array_column($data, 'activo');
        $this->assertSame([true, true, false], $activos, 'activos primero');
        $this->assertSame($alfa->id, $data[2]['user_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-0[56]:00$/', (string) $data[0]['asignado_at']);

        $json = (string) $r->getContent();
        foreach (['password', 'remember_token', 'phone', 'address', 'portal_password'] as $sensible) {
            $this->assertStringNotContainsString('"' . $sensible . '"', $json, "no debe exponer {$sensible}");
        }
    }

    public function test_index_de_empresa_ajena_da_404(): void
    {
        $this->comoAdminB()->getJson('/api/marketing/empresas/1/usuarios')->assertStatus(404);
        $this->comoAdminB()->getJson('/api/marketing/empresas/999999999/usuarios')->assertStatus(404);
    }

    // ── POST usuarios ────────────────────────────────────────────────────────

    public function test_agregar_persona_201_con_created_by(): void
    {
        $r = $this->comoAdminB()->postJson($this->urlB(), ['user_id' => $this->candidato->id, 'rol' => 'ventas']);
        $r->assertStatus(201)->assertJsonPath('ok', true)
            ->assertJsonPath('data.user_id', $this->candidato->id)
            ->assertJsonPath('data.rol', 'ventas')
            ->assertJsonPath('data.activo', true);

        $fila = DB::table('marketing_empresa_usuario')->where('company_id', $this->empresaB->id)->where('user_id', $this->candidato->id)->first();
        $this->assertNotNull($fila);
        $this->assertSame($this->adminB->id, (int) $fila->created_by);
    }

    public function test_agregar_ignora_company_id_del_request(): void
    {
        $this->comoAdminB()->postJson($this->urlB(), ['user_id' => $this->candidato->id, 'rol' => 'ventas', 'company_id' => 1])
            ->assertStatus(201);

        $this->assertSame(0, DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $this->candidato->id)->count());
        $this->assertSame(1, DB::table('marketing_empresa_usuario')->where('company_id', $this->empresaB->id)->where('user_id', $this->candidato->id)->count());
    }

    public function test_agregar_dos_veces_da_422_ya_asignado_y_una_sola_fila(): void
    {
        $this->comoAdminB()->postJson($this->urlB(), ['user_id' => $this->candidato->id, 'rol' => 'ventas'])->assertStatus(201);
        $this->contexto()->limpiar();
        $r = $this->postJson($this->urlB(), ['user_id' => $this->candidato->id, 'rol' => 'admin']);

        $r->assertStatus(422)->assertExactJson(['ok' => false, 'error' => 'ya_asignado', 'mensaje' => self::MSG_YA_ASIGNADO]);
        $this->assertSame(1, DB::table('marketing_empresa_usuario')->where('company_id', $this->empresaB->id)->where('user_id', $this->candidato->id)->count());
        $this->assertSame('ventas', DB::table('marketing_empresa_usuario')->where('company_id', $this->empresaB->id)->where('user_id', $this->candidato->id)->value('rol'));
    }

    public function test_agregar_a_quien_estaba_inactivo_lo_reactiva_con_200_y_rol_nuevo(): void
    {
        $this->asignar($this->candidato, $this->empresaB->id, 'ventas', activo: false);

        $r = $this->comoAdminB()->postJson($this->urlB(), ['user_id' => $this->candidato->id, 'rol' => 'marketing']);
        $r->assertStatus(200)->assertJsonPath('data.rol', 'marketing')->assertJsonPath('data.activo', true);

        $this->assertSame(1, DB::table('marketing_empresa_usuario')->where('company_id', $this->empresaB->id)->where('user_id', $this->candidato->id)->count());
    }

    public function test_agregar_como_admin_no_da_roles_ni_permisos_spatie(): void
    {
        $antes = $this->spatieDe($this->candidato);
        $this->comoAdminB()->postJson($this->urlB(), ['user_id' => $this->candidato->id, 'rol' => 'admin'])->assertStatus(201);
        $this->assertSame($antes, $this->spatieDe($this->candidato), 'R10: la pantalla solo escribe marketing_empresa_usuario');
    }

    public function test_agregar_usuarios_no_validos_da_422_en_user_id(): void
    {
        $sistema = $this->crearUsuario('qa_bot', [], superAdmin: false, permisosMarketing: false, conGestionar: false, extra: ['is_system' => 1]);
        $sistema->assignRole('Vendedor');
        $inactivo = $this->crearUsuario('qa_inactivo', [], superAdmin: false, permisosMarketing: false, conGestionar: false, extra: ['estado' => 'inactivo']);
        $inactivo->assignRole('Vendedor');
        $bloqueado = $this->crearUsuario('qa_bloq', [], superAdmin: false, permisosMarketing: false, conGestionar: false, extra: ['estado' => 'bloqueado']);
        $bloqueado->assignRole('Vendedor');
        $soloClient = $this->crearUsuario('qa_client', [], superAdmin: false, permisosMarketing: false, conGestionar: false);
        $soloClient->assignRole('client');
        $sinRol = $this->crearUsuario('qa_sinrol', [], superAdmin: false, permisosMarketing: false, conGestionar: false);

        foreach ([
            'cuenta de sistema' => $sistema->id,
            'inactivo'          => $inactivo->id,
            'bloqueado'         => $bloqueado->id,
            'solo client'       => $soloClient->id,
            'sin ningún rol'    => $sinRol->id,
            'inexistente'       => 999999999,
            'texto'             => 'abc',
            'array'             => [$this->candidato->id],
            'sql'               => '1 OR 1=1',
            'negativo'          => -1,
            'nulo'              => null,
        ] as $que => $userId) {
            $r = $this->comoAdminB()->postJson($this->urlB(), ['user_id' => $userId, 'rol' => 'ventas']);
            if ($que === 'sql') {
                $this->assertContains($r->status(), [403, 422], "{$que}: WAF (403) o validación (422), llegó {$r->status()}");
                continue;
            }
            $this->assertSame(422, $r->status(), "{$que}: esperaba 422, llegó {$r->status()}");
            $r->assertJsonValidationErrors(['user_id']);
        }

        $this->assertSame(1, DB::table('marketing_empresa_usuario')->where('company_id', $this->empresaB->id)->count(), 'solo sigue el admin de B');
    }

    public function test_agregar_con_rol_invalido_da_422(): void
    {
        foreach (['superadmin', 'ADMIN', 'Admin', '', 'cliente', '<script>', ['admin'], 'admin,ventas'] as $rol) {
            $r = $this->comoAdminB()->postJson($this->urlB(), ['user_id' => $this->candidato->id, 'rol' => $rol]);
            $this->assertSame(422, $r->status(), 'rol=' . json_encode($rol));
            $r->assertJsonValidationErrors(['rol']);
        }
        $this->comoAdminB()->postJson($this->urlB(), ['user_id' => $this->candidato->id])->assertStatus(422)->assertJsonValidationErrors(['rol']);
    }

    public function test_agregar_en_empresa_ajena_da_404_y_no_escribe(): void
    {
        $this->comoAdminB()->postJson('/api/marketing/empresas/1/usuarios', ['user_id' => $this->candidato->id, 'rol' => 'admin'])
            ->assertStatus(404);
        $this->assertSame(0, DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $this->candidato->id)->count());
    }

    public function test_admin_de_b_no_puede_agregarse_a_si_mismo_en_a(): void
    {
        $this->comoAdminB()->postJson('/api/marketing/empresas/1/usuarios', ['user_id' => $this->adminB->id, 'rol' => 'admin'])
            ->assertStatus(404);
        $this->assertSame(0, DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $this->adminB->id)->count());
    }

    // ── PUT usuarios/{userId} ────────────────────────────────────────────────

    public function test_cambiar_rol_y_desactivar(): void
    {
        $this->asignar($this->candidato, $this->empresaB->id, 'ventas');

        $this->comoAdminB()->putJson($this->urlB("/{$this->candidato->id}"), ['rol' => 'marketing'])
            ->assertOk()->assertJsonPath('data.rol', 'marketing');
        $this->comoAdminB()->putJson($this->urlB("/{$this->candidato->id}"), ['activo' => false])
            ->assertOk()->assertJsonPath('data.activo', false);

        $fila = DB::table('marketing_empresa_usuario')->where('company_id', $this->empresaB->id)->where('user_id', $this->candidato->id)->first();
        $this->assertSame('marketing', $fila->rol);
        $this->assertSame(0, (int) $fila->activo, 'quitar = activo false, la fila no se borra');
    }

    public function test_ultimo_admin_no_se_puede_bajar_de_rol_ni_desactivar_ni_a_si_mismo(): void
    {
        foreach ([['rol' => 'marketing'], ['rol' => 'ventas'], ['activo' => false], ['rol' => 'ventas', 'activo' => false]] as $cuerpo) {
            $r = $this->comoAdminB()->putJson($this->urlB("/{$this->adminB->id}"), $cuerpo);
            $r->assertStatus(422)->assertExactJson(['ok' => false, 'error' => 'ultimo_admin', 'mensaje' => self::MSG_ULTIMO_ADMIN]);
        }

        $fila = DB::table('marketing_empresa_usuario')->where('company_id', $this->empresaB->id)->where('user_id', $this->adminB->id)->first();
        $this->assertSame('admin', $fila->rol);
        $this->assertSame(1, (int) $fila->activo);
    }

    public function test_con_dos_admins_se_puede_quitar_uno_pero_no_el_segundo(): void
    {
        $this->asignar($this->candidato, $this->empresaB->id, 'admin');

        $this->comoAdminB()->putJson($this->urlB("/{$this->candidato->id}"), ['activo' => false])->assertOk();
        $this->comoAdminB()->putJson($this->urlB("/{$this->adminB->id}"), ['rol' => 'marketing'])
            ->assertStatus(422)->assertJsonPath('error', 'ultimo_admin');
    }

    public function test_un_admin_inactivo_no_cuenta_como_admin(): void
    {
        $this->asignar($this->candidato, $this->empresaB->id, 'admin', activo: false);

        $this->comoAdminB()->putJson($this->urlB("/{$this->adminB->id}"), ['activo' => false])
            ->assertStatus(422)->assertJsonPath('error', 'ultimo_admin');
    }

    public function test_reactivar_o_subir_a_admin_si_se_puede(): void
    {
        $this->asignar($this->candidato, $this->empresaB->id, 'ventas', activo: false);
        $this->comoAdminB()->putJson($this->urlB("/{$this->candidato->id}"), ['activo' => true, 'rol' => 'admin'])
            ->assertOk()->assertJsonPath('data.rol', 'admin')->assertJsonPath('data.activo', true);
    }

    public function test_actualizar_con_rol_invalido_da_422(): void
    {
        $this->asignar($this->candidato, $this->empresaB->id, 'ventas');
        $this->comoAdminB()->putJson($this->urlB("/{$this->candidato->id}"), ['rol' => 'dios'])
            ->assertStatus(422)->assertJsonValidationErrors(['rol']);
        $this->comoAdminB()->putJson($this->urlB("/{$this->candidato->id}"), ['activo' => 'quizá'])
            ->assertStatus(422)->assertJsonValidationErrors(['activo']);
    }

    public function test_actualizar_persona_sin_fila_en_esta_empresa_da_404(): void
    {
        // marketingA está en A pero no en B.
        $this->comoAdminB()->putJson($this->urlB("/{$this->marketingA->id}"), ['rol' => 'ventas'])->assertStatus(404);
        $this->comoAdminB()->putJson($this->urlB('/999999999'), ['rol' => 'ventas'])->assertStatus(404);
        $this->assertSame('marketing', DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $this->marketingA->id)->value('rol'));
    }

    public function test_actualizar_en_empresa_ajena_da_404(): void
    {
        $this->comoAdminB()->putJson("/api/marketing/empresas/1/usuarios/{$this->marketingA->id}", ['activo' => false])->assertStatus(404);
        $this->assertSame(1, (int) DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $this->marketingA->id)->value('activo'));
    }

    public function test_actualizar_ignora_company_id_y_user_id_del_cuerpo(): void
    {
        $this->asignar($this->candidato, $this->empresaB->id, 'ventas');
        $this->comoAdminB()->putJson($this->urlB("/{$this->candidato->id}"), ['rol' => 'marketing', 'company_id' => 1, 'user_id' => $this->adminA->id])
            ->assertOk();

        $this->assertSame('marketing', DB::table('marketing_empresa_usuario')->where('company_id', $this->empresaB->id)->where('user_id', $this->candidato->id)->value('rol'));
        $this->assertSame('admin', DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $this->adminA->id)->value('rol'));
    }

    // ── GET usuarios-disponibles ─────────────────────────────────────────────

    public function test_disponibles_forma_exacta_y_sin_campos_sensibles(): void
    {
        $r = $this->comoAdminB()->getJson($this->urlB('-disponibles') . '?buscar=' . urlencode('Diana QA'));
        $r->assertOk()->assertJsonPath('ok', true);

        $fila = collect($r->json('data'))->firstWhere('id', $this->candidato->id);
        $this->assertNotNull($fila, 'debe encontrar al candidato por nombre');
        $this->assertSame(['id', 'nombre', 'login_user', 'email'], array_keys($fila));
    }

    public function test_disponibles_busca_por_login_y_correo(): void
    {
        foreach ([$this->candidato->login_user, $this->candidato->email] as $buscar) {
            $ids = array_column($this->comoAdminB()->getJson($this->urlB('-disponibles') . '?buscar=' . urlencode($buscar))->json('data') ?? [], 'id');
            $this->assertContains($this->candidato->id, $ids, "buscar={$buscar}");
        }
    }

    public function test_disponibles_excluye_miembros_activos_sistema_inactivos_y_solo_client(): void
    {
        $tag = 'Zqx' . Str::random(5);
        $miembroActivo = $this->crearUsuario('qa_m1', [], extra: ['name' => "{$tag} Activo"]);
        $this->asignar($miembroActivo, $this->empresaB->id, 'ventas');
        $miembroInactivo = $this->crearUsuario('qa_m2', [], extra: ['name' => "{$tag} ExMiembro"]);
        $this->asignar($miembroInactivo, $this->empresaB->id, 'ventas', activo: false);
        $deOtraEmpresa = $this->crearUsuario('qa_m3', [1 => 'ventas'], extra: ['name' => "{$tag} DeA"]);
        $bot = $this->crearUsuario('qa_m4', [], extra: ['name' => "{$tag} Bot", 'is_system' => 1]);
        $baja = $this->crearUsuario('qa_m5', [], extra: ['name' => "{$tag} Baja", 'estado' => 'inactivo']);
        $cliente = $this->crearUsuario('qa_m6', [], superAdmin: false, permisosMarketing: false, conGestionar: false, extra: ['name' => "{$tag} Cliente"]);
        $cliente->assignRole('client');

        $ids = array_column($this->comoAdminB()->getJson($this->urlB('-disponibles') . '?buscar=' . $tag)->assertOk()->json('data'), 'id');

        $this->assertNotContains($miembroActivo->id, $ids);
        $this->assertContains($miembroInactivo->id, $ids, 'el que fue miembro y está inactivo sí se puede volver a agregar');
        $this->assertContains($deOtraEmpresa->id, $ids);
        $this->assertNotContains($bot->id, $ids);
        $this->assertNotContains($baja->id, $ids);
        $this->assertNotContains($cliente->id, $ids);
    }

    public function test_disponibles_maximo_20(): void
    {
        $tag = 'Lote' . Str::random(5);
        for ($i = 0; $i < 25; $i++) {
            $u = User::factory()->create(['name' => "{$tag} {$i}", 'estado' => 'activo', 'is_system' => 0, 'login_user' => 'qa_lote_' . Str::lower(Str::random(8))]);
            $u->assignRole('Vendedor');
        }

        $this->assertCount(20, $this->comoAdminB()->getJson($this->urlB('-disponibles') . '?buscar=' . $tag)->assertOk()->json('data'));
    }

    public function test_disponibles_buscar_fuera_de_rango_da_422(): void
    {
        foreach (['', 'a', str_repeat('b', 61)] as $buscar) {
            $this->comoAdminB()->getJson($this->urlB('-disponibles') . '?buscar=' . urlencode($buscar))
                ->assertStatus(422)->assertJsonValidationErrors(['buscar']);
        }
        $this->comoAdminB()->getJson($this->urlB('-disponibles'))->assertStatus(422)->assertJsonValidationErrors(['buscar']);
        $this->comoAdminB()->getJson($this->urlB('-disponibles') . '?buscar[]=ab')->assertStatus(422);
    }

    public function test_disponibles_inyeccion_sql_no_devuelve_de_mas_ni_truena(): void
    {
        foreach (["' OR '1'='1", "x' UNION SELECT password,1,1,1 FROM users -- ", '") OR 1=1 #', "'; DROP TABLE users; --"] as $ataque) {
            $r = $this->comoAdminB()->getJson($this->urlB('-disponibles') . '?buscar=' . urlencode($ataque));
            // El WAF global puede cortar con 403; si deja pasar, el binding no debe devolver nada.
            $this->assertContains($r->status(), [200, 403], 'buscar=' . $ataque . ' → ' . $r->status());
            if ($r->status() === 200) {
                $this->assertSame([], $r->json('data'), 'buscar=' . $ataque);
            }
        }
        $this->assertGreaterThan(0, DB::table('users')->count(), 'users sigue viva');
    }

    public function test_disponibles_de_empresa_ajena_da_404(): void
    {
        $this->comoAdminB()->getJson('/api/marketing/empresas/1/usuarios-disponibles?buscar=qa')->assertStatus(404);
    }
}
