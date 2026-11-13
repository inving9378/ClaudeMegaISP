<?php

namespace Tests\Feature\Marketing\Fase0\Qa;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Marketing\Fase0\Qa\Concerns\EscenarioEmpresas;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 0 — API de contexto, rutas nuevas 1 y 2 (§2.6).
 * Forma exacta, cambio de empresa, 404 de empresa ajena / inactiva / inexistente, 422, sesión basura.
 */
class ContextoEmpresaApiTest extends MarketingTestCase
{
    use EscenarioEmpresas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenario(conDatos: false);
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    // ── GET /api/marketing/contexto ──────────────────────────────────────────

    public function test_forma_exacta_del_contexto(): void
    {
        $r = $this->como($this->adminA)->getJson('/api/marketing/contexto');
        $r->assertOk();

        $r->assertJsonStructure([
            'ok',
            'usuario' => ['id', 'nombre'],
            'empresa_activa' => ['id', 'nombre', 'slug', 'color_primario', 'color_secundario', 'rol'],
            'empresas' => [['id', 'nombre', 'slug', 'color_primario', 'rol']],
            'capacidades' => ['gestionar_empresas', 'editar_config_compartida'],
        ]);
        $this->assertSame(['ok', 'usuario', 'empresa_activa', 'empresas', 'capacidades'], array_keys($r->json()));
        $this->assertSame(['id', 'nombre'], array_keys($r->json('usuario')));
        $this->assertSame(['gestionar_empresas', 'editar_config_compartida'], array_keys($r->json('capacidades')));

        $r->assertJsonPath('ok', true);
        $r->assertJsonPath('usuario.id', $this->adminA->id);
        $r->assertJsonPath('empresa_activa.id', 1);
        $r->assertJsonPath('empresa_activa.nombre', 'Meganet');
        $r->assertJsonPath('empresa_activa.slug', 'meganet');
        $r->assertJsonPath('empresa_activa.rol', 'admin');
        $r->assertJsonPath('capacidades.gestionar_empresas', true);
        $r->assertJsonPath('capacidades.editar_config_compartida', true);
        $this->assertCount(1, $r->json('empresas'));
    }

    public function test_capacidades_por_rol(): void
    {
        $casos = [
            'marketing A'      => [$this->marketingA, null, false, true],
            'ventas A'         => [$this->ventasA, null, false, false],
            'admin B'          => [$this->adminB, null, true, false],
            'admin A sin perm' => [$this->adminASinPermiso, null, false, true],
        ];
        foreach ($casos as $que => [$u, $emp, $gestionar, $editar]) {
            $r = $this->como($u, $emp)->getJson('/api/marketing/contexto');
            $r->assertOk();
            $this->assertSame($gestionar, $r->json('capacidades.gestionar_empresas'), "{$que}: gestionar_empresas");
            $this->assertSame($editar, $r->json('capacidades.editar_config_compartida'), "{$que}: editar_config_compartida");
        }
    }

    public function test_lista_solo_asignaciones_activas_de_empresas_activas_por_id(): void
    {
        $inactiva = $this->crearEmpresa('QA Inactiva', activo: false);
        $otra = $this->crearEmpresa('QA Otra');
        $u = $this->crearUsuario('qa_varias', [$this->empresaB->id => 'ventas', 1 => 'marketing', $inactiva->id => 'admin']);
        $this->asignar($u, $otra->id, 'admin', activo: false);

        $r = $this->como($u)->getJson('/api/marketing/contexto');
        $r->assertOk();

        $this->assertSame([1, $this->empresaB->id], array_column($r->json('empresas'), 'id'), 'ordenadas por id, sin inactivas');
        $this->assertSame(['marketing', 'ventas'], array_column($r->json('empresas'), 'rol'));
    }

    public function test_sin_sesion_elige_la_de_menor_id_y_la_guarda_en_sesion(): void
    {
        $u = $this->crearUsuario('qa_dos', [$this->empresaB->id => 'admin', 1 => 'ventas']);

        $r = $this->como($u)->getJson('/api/marketing/contexto');
        $r->assertOk()->assertJsonPath('empresa_activa.id', 1)->assertJsonPath('empresa_activa.rol', 'ventas');
        $this->assertSame(1, (int) session('marketing.empresa_activa_id'));
    }

    public function test_respeta_la_empresa_guardada_en_sesion(): void
    {
        $u = $this->crearUsuario('qa_dos_b', [1 => 'ventas', $this->empresaB->id => 'admin']);

        $this->como($u, $this->empresaB->id)->getJson('/api/marketing/contexto')
            ->assertOk()->assertJsonPath('empresa_activa.id', $this->empresaB->id)->assertJsonPath('empresa_activa.rol', 'admin');
    }

    public static function sesionesBasura(): array
    {
        return [
            'texto'          => ['abc'],
            'negativo'       => [-5],
            'inexistente'    => [999999999],
            'sql'            => ["1 OR 1=1"],
            'array'          => [[1, 2]],
            'vacío'          => [''],
        ];
    }

    /** @dataProvider sesionesBasura */
    public function test_sesion_con_id_basura_cae_a_una_valida(mixed $valor): void
    {
        $this->contexto()->limpiar();
        $this->flushSession();
        $r = $this->actingAs($this->ventasA)->withSession(['marketing.empresa_activa_id' => $valor])->getJson('/api/marketing/contexto');

        $r->assertOk()->assertJsonPath('empresa_activa.id', 1);
    }

    public function test_sesion_con_empresa_ajena_no_da_acceso_a_ella(): void
    {
        // ventasA tiene en sesión el id de B (no es suyo): se ignora.
        $this->como($this->ventasA, $this->empresaB->id)->getJson('/api/marketing/contexto')
            ->assertOk()->assertJsonPath('empresa_activa.id', 1);
        $this->assertSame(1, (int) session('marketing.empresa_activa_id'));
    }

    // ── POST /api/marketing/contexto/empresa-activa ──────────────────────────

    public function test_cambiar_de_empresa_guarda_en_sesion_y_responde_la_misma_forma(): void
    {
        $u = $this->crearUsuario('qa_cambio', [1 => 'admin', $this->empresaB->id => 'ventas']);

        $r = $this->como($u)->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => $this->empresaB->id]);
        $r->assertOk();
        $this->assertSame(['ok', 'usuario', 'empresa_activa', 'empresas', 'capacidades'], array_keys($r->json()));
        $r->assertJsonPath('empresa_activa.id', $this->empresaB->id)->assertJsonPath('empresa_activa.rol', 'ventas');
        $r->assertJsonPath('capacidades.gestionar_empresas', false);
        $r->assertJsonPath('capacidades.editar_config_compartida', false);
        $this->assertSame($this->empresaB->id, (int) session('marketing.empresa_activa_id'));

        // La siguiente petición (misma sesión) ya trae B.
        $this->contexto()->limpiar();
        $this->getJson('/api/marketing/contexto')->assertOk()->assertJsonPath('empresa_activa.id', $this->empresaB->id);
    }

    public function test_cambiar_a_la_misma_empresa_es_idempotente(): void
    {
        $this->como($this->adminA)->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => 1])
            ->assertOk()->assertJsonPath('empresa_activa.id', 1);
        $this->contexto()->limpiar();
        $this->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => 1])
            ->assertOk()->assertJsonPath('empresa_activa.id', 1);
    }

    public function test_cambiar_acepta_company_id_como_texto_numerico(): void
    {
        $u = $this->crearUsuario('qa_txt', [1 => 'admin', $this->empresaB->id => 'admin']);
        $this->como($u)->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => (string) $this->empresaB->id])
            ->assertOk()->assertJsonPath('empresa_activa.id', $this->empresaB->id);
    }

    public function test_cambiar_a_empresa_ajena_inactiva_o_inexistente_da_404_y_no_toca_la_sesion(): void
    {
        $inactiva = $this->crearEmpresa('QA Inact', activo: false);
        $u = $this->crearUsuario('qa_404', [1 => 'admin', $inactiva->id => 'admin']);
        $otra = $this->crearEmpresa('QA Otra 2');
        $this->asignar($u, $otra->id, 'admin', activo: false);

        foreach ([
            'ajena (B)'             => $this->empresaB->id,
            'empresa inactiva'      => $inactiva->id,
            'asignación inactiva'   => $otra->id,
            'inexistente'           => 999999999,
        ] as $que => $id) {
            $r = $this->como($u, 1)->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => $id]);
            $this->assertSame(404, $r->status(), "{$que}: esperaba 404, llegó {$r->status()}");
            $this->assertSame(1, (int) session('marketing.empresa_activa_id'), "{$que}: no debe cambiar la sesión");
        }
    }

    public static function companyIdInvalido(): array
    {
        return [
            'faltante'      => [null, false],
            'texto'         => ['abc', true],
            'decimal'       => [1.5, true],
            'array'         => [[1], true],
            'sql'           => ['1; DROP TABLE marketing_empresas', true],
            'vacío'         => ['', true],
        ];
    }

    /** @dataProvider companyIdInvalido */
    public function test_cambiar_con_company_id_invalido_da_422(mixed $valor, bool $enviar): void
    {
        $cuerpo = $enviar ? ['company_id' => $valor] : [];
        $r = $this->como($this->adminA, 1)->postJson('/api/marketing/contexto/empresa-activa', $cuerpo);

        if (is_string($valor) && str_contains($valor, 'DROP')) {
            // El WAF global (SqlInjectionProtection) puede cortar antes con 403; ambos son rechazos correctos.
            $this->assertContains($r->status(), [403, 422]);
        } else {
            $r->assertStatus(422);
            $r->assertJsonValidationErrors(['company_id']);
        }
        $this->assertSame(1, (int) session('marketing.empresa_activa_id'));
        $this->assertSame(1, DB::table('marketing_empresas')->where('id', 1)->count(), 'la tabla sigue viva');
    }

    public function test_cambiar_a_cero_o_negativo_se_rechaza(): void
    {
        // Contrato: no entero → 422; entero sin asignación → 404. 0 y -1 son enteros, pero un `min:1` es
        // igual de correcto: se acepta cualquiera de los dos, nunca 200.
        foreach ([0, -1] as $id) {
            $r = $this->como($this->adminA, 1)->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => $id]);
            $this->assertContains($r->status(), [404, 422], "company_id={$id} → {$r->status()}");
            $this->assertSame(1, (int) session('marketing.empresa_activa_id'));
        }
    }

    public function test_cambiar_no_permite_a_ventas_colarse_a_otra_empresa(): void
    {
        $this->como($this->ventasA)->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => $this->empresaB->id])
            ->assertStatus(404);
        $this->contexto()->limpiar();
        $this->getJson('/api/marketing/contexto')->assertJsonPath('empresa_activa.id', 1);
    }

    public function test_doble_envio_de_cambio_no_rompe_nada(): void
    {
        $u = $this->crearUsuario('qa_doble', [1 => 'admin', $this->empresaB->id => 'admin']);
        $this->como($u)->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => $this->empresaB->id])->assertOk();
        $this->contexto()->limpiar();
        $this->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => $this->empresaB->id])->assertOk();

        $this->assertSame(2, DB::table('marketing_empresa_usuario')->where('user_id', $u->id)->count(), 'cambiar no escribe asignaciones');
    }
}
