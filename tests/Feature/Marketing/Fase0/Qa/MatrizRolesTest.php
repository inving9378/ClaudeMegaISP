<?php

namespace Tests\Feature\Marketing\Fase0\Qa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Marketing\Fase0\Qa\Concerns\EscenarioEmpresas;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 0 — matriz de roles (§2.5, §2.7, D2, criterios 8 y 9).
 *
 * admin / marketing / ventas / sin empresa / empresa inactiva / asignación inactiva contra una ruta
 * representativa de cada grupo V, M, MP y de las nuevas; JSON y navegación de página;
 * `config_compartida` con la empresa B activa.
 *
 * Nota: las pruebas de página dependen de que el layout `core-layout::master` renderice en el
 * worktree (necesita `public/js/app.js` compilado por frontend, §2.10.1). Las de JSON no.
 */
class MatrizRolesTest extends MarketingTestCase
{
    use EscenarioEmpresas;

    private const MSG_SIN_EMPRESA = 'Todavía no perteneces a ninguna empresa de Marketing. Pide a un administrador de Marketing que te agregue en «Empresas y equipo».';
    private const MSG_SIN_PERMISO_ROL = 'Tu rol en esta empresa no permite esta acción.';
    private const MSG_CONFIG_COMPARTIDA = 'Esta configuración es de Meganet y la comparten todas las empresas; solo se cambia con Meganet como empresa activa.';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::fake();
        $this->montarEscenario();
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function assertNegativa(TestResponse $r, string $codigo, string $mensaje): void
    {
        $r->assertStatus(403);
        $r->assertExactJson(['ok' => false, 'error' => $codigo, 'mensaje' => $mensaje]);
    }

    private function assertPermitido(TestResponse $r, string $que): void
    {
        $this->assertNotContains($r->status(), [401, 403, 404], "{$que}: respondió {$r->status()} " . substr((string) $r->getContent(), 0, 300));
        $this->assertNotSame('sin_empresa', $r->json('error'));
        $this->assertNotSame('sin_permiso_rol', $r->json('error'));
        $this->assertNotSame('config_compartida', $r->json('error'));
    }

    // ── sin sesión ───────────────────────────────────────────────────────────

    public function test_sin_sesion_api_401_y_pagina_a_login(): void
    {
        $this->getJson('/api/marketing/leads')->assertStatus(401);
        $this->getJson('/api/marketing/contexto')->assertStatus(401);
        $this->get('/marketing/empresas')->assertRedirect();
    }

    // ── sin empresa ──────────────────────────────────────────────────────────

    public static function rutasJsonRepresentativas(): array
    {
        return [
            'V  GET leads'               => ['GET', '/api/marketing/leads'],
            'V  GET conversations'       => ['GET', '/api/marketing/conversations'],
            'M  GET multivariant'        => ['GET', '/api/marketing/multivariant-campaigns'],
            'M  GET publishing (crp)'    => ['GET', '/api/marketing/publishing/publications'],
            'MP PUT brand-kit'           => ['PUT', '/api/marketing/brand-kit'],
            'N  GET contexto'            => ['GET', '/api/marketing/contexto'],
            'N  GET empresas'            => ['GET', '/api/marketing/empresas'],
            'N  POST cambiar empresa'    => ['POST', '/api/marketing/contexto/empresa-activa'],
        ];
    }

    /** @dataProvider rutasJsonRepresentativas */
    public function test_sin_empresa_recibe_sin_empresa_en_json(string $metodo, string $url): void
    {
        $r = $this->como($this->sinEmpresa)->json($metodo, $url, ['company_id' => 1]);
        $this->assertNegativa($r, 'sin_empresa', self::MSG_SIN_EMPRESA);
    }

    public function test_sin_empresa_pagina_muestra_aun_no_tienes_empresa(): void
    {
        $r = $this->como($this->sinEmpresa)->get('/marketing/leads');
        $r->assertStatus(403);
        $r->assertSee('Aún no tienes empresa en Marketing');
    }

    public function test_super_admin_sin_asignacion_no_tiene_atajo(): void
    {
        // sinEmpresa tiene super-administrator (pasa check_route_permission) y aun así no entra (D1).
        $this->assertTrue($this->sinEmpresa->hasRole('super-administrator'));
        $this->assertNegativa($this->como($this->sinEmpresa)->getJson('/api/marketing/leads'), 'sin_empresa', self::MSG_SIN_EMPRESA);
    }

    public function test_asignacion_inactiva_cuenta_como_sin_empresa(): void
    {
        $u = $this->crearUsuario('qa_asig_inactiva', []);
        $this->asignar($u, 1, 'admin', activo: false);

        $this->assertNegativa($this->como($u)->getJson('/api/marketing/leads'), 'sin_empresa', self::MSG_SIN_EMPRESA);
    }

    public function test_empresa_inactiva_cuenta_como_sin_empresa(): void
    {
        $inactiva = $this->crearEmpresa('QA Inactiva', activo: false);
        $u = $this->crearUsuario('qa_emp_inactiva', [$inactiva->id => 'admin']);

        $this->assertNegativa($this->como($u, $inactiva->id)->getJson('/api/marketing/leads'), 'sin_empresa', self::MSG_SIN_EMPRESA);
    }

    public function test_sesion_apuntando_a_empresa_inactiva_cae_a_la_activa(): void
    {
        $inactiva = $this->crearEmpresa('QA Inactiva 2', activo: false);
        $u = $this->crearUsuario('qa_mixto', [1 => 'ventas', $inactiva->id => 'admin']);

        $r = $this->como($u, $inactiva->id)->getJson('/api/marketing/contexto');
        $r->assertOk();
        $this->assertSame(1, $r->json('empresa_activa.id'));
        $this->assertSame('ventas', $r->json('empresa_activa.rol'));
    }

    // ── V: ventas entra a leads y conversaciones (D2) ────────────────────────

    public function test_ventas_ve_crea_edita_e_historial_de_leads_y_conversaciones(): void
    {
        $lead = $this->a['lead'];
        $this->assertPermitido($this->como($this->ventasA)->getJson('/api/marketing/leads'), 'ventas GET leads');
        $this->assertPermitido($this->como($this->ventasA)->getJson("/api/marketing/leads/{$lead->id}"), 'ventas GET lead');
        $this->assertPermitido($this->como($this->ventasA)->getJson("/api/marketing/leads/{$lead->id}/activities"), 'ventas historial');
        $this->assertPermitido(
            $this->como($this->ventasA)->putJson("/api/marketing/leads/{$lead->id}", ['notes' => 'editado por ventas']),
            'ventas PUT lead'
        );
        $alta = $this->como($this->ventasA)->postJson('/api/marketing/leads', ['source_id' => $this->fuente->id, 'full_name' => 'Alta de ventas']);
        $this->assertSame(201, $alta->status(), 'ventas POST lead: ' . substr((string) $alta->getContent(), 0, 300));

        $this->assertPermitido($this->como($this->ventasA)->getJson('/api/marketing/conversations'), 'ventas GET conversations');
        $this->assertPermitido($this->como($this->ventasA)->getJson("/api/marketing/conversations/{$this->a['conversacion']->id}"), 'ventas GET conversation');
        $this->assertPermitido($this->como($this->ventasA)->getJson("/api/marketing/conversations/{$this->a['conversacion']->id}/messages"), 'ventas mensajes');
        $this->assertPermitido($this->como($this->ventasA)->getJson('/api/marketing/lead-sources'), 'ventas lead-sources');
    }

    // ── M: ventas recibe sin_permiso_rol ─────────────────────────────────────

    public static function rutasSoloMarketing(): array
    {
        return [
            'borrar lead'         => ['DELETE', '/api/marketing/leads/{lead}'],
            'reasignar lead'      => ['POST', '/api/marketing/leads/{lead}/assign'],
            'puntaje IA'          => ['POST', '/api/marketing/leads/{lead}/score'],
            'multivariante'       => ['GET', '/api/marketing/multivariant-campaigns'],
            'video generado'      => ['GET', '/api/marketing/generated-content'],
            'plantillas de video' => ['GET', '/api/marketing/video-templates'],
            'publicación'         => ['GET', '/api/marketing/publishing/publications'],
            'stats publicación'   => ['GET', '/api/marketing/publishing/dashboard/stats'],
            'piloto'              => ['GET', '/api/marketing/pilot-campaigns'],
            'formularios'         => ['GET', '/api/marketing/lead-forms'],
            'brand kit (lectura)' => ['GET', '/api/marketing/brand-kit'],
            'nichos'              => ['GET', '/api/marketing/niches'],
            'campañas legado'     => ['PUT', '/marketing/campaigns/{campana}'],
        ];
    }

    /** @dataProvider rutasSoloMarketing */
    public function test_ventas_recibe_sin_permiso_rol_en_rutas_de_marketing(string $metodo, string $plantilla): void
    {
        $url = str_replace(['{lead}', '{campana}'], [$this->a['lead']->id, $this->a['campana']->id], $plantilla);
        $antes = $this->fila('marketing_leads', $this->a['lead']->id);

        $r = $this->como($this->ventasA)->json($metodo, $url, ['user_id' => $this->ventasA->id]);
        $this->assertNegativa($r, 'sin_permiso_rol', self::MSG_SIN_PERMISO_ROL);

        $this->assertEquals($antes, $this->fila('marketing_leads', $this->a['lead']->id), 'el lead no debe cambiar');
        Queue::assertNothingPushed();
    }

    /** @dataProvider rutasSoloMarketing */
    public function test_marketing_si_entra_a_rutas_de_marketing(string $metodo, string $plantilla): void
    {
        if ($metodo !== 'GET') {
            $this->assertTrue(true); // las de escritura se cubren en aislamiento; aquí solo lectura
            return;
        }
        $url = str_replace(['{lead}', '{campana}'], [$this->a['lead']->id, $this->a['campana']->id], $plantilla);
        $this->assertPermitido($this->como($this->marketingA)->getJson($url), "marketing GET {$url}");
    }

    public static function paginasSoloMarketing(): array
    {
        return [
            'dashboard'       => ['/marketing'],
            'video'           => ['/marketing/video-queue'],
            'generador video' => ['/marketing/video-generator'],
            'campañas'        => ['/marketing/campaigns/generate'],
            'publicación'     => ['/marketing/publishing'],
            'piloto'          => ['/marketing/pilot-campaigns'],
            'formularios'     => ['/marketing/lead-forms'],
        ];
    }

    /** @dataProvider paginasSoloMarketing */
    public function test_ventas_pagina_de_marketing_da_403(string $url): void
    {
        $this->como($this->ventasA)->get($url)->assertStatus(403);
    }

    public function test_ventas_paginas_de_leads_y_conversaciones_no_dan_403(): void
    {
        foreach (['/marketing/leads', "/marketing/leads/{$this->a['lead']->id}", '/marketing/conversations'] as $url) {
            $r = $this->como($this->ventasA)->get($url);
            $this->assertNotContains($r->status(), [401, 403, 404], "ventas {$url} → {$r->status()}");
        }
    }

    // ── MP: config compartida ────────────────────────────────────────────────

    public static function rutasConfigCompartida(): array
    {
        return [
            'brand-kit'          => ['PUT', '/api/marketing/brand-kit', []],
            'integraciones'      => ['PUT', '/api/marketing/brand-kit/integrations', []],
            'logo subir'         => ['POST', '/api/marketing/brand-kit/logo', []],
            'logo borrar'        => ['DELETE', '/api/marketing/brand-kit/logo', []],
            'nicho'              => ['PUT', '/api/marketing/niches/1', ['name' => 'hack']],
            'voz de nicho'       => ['POST', '/api/marketing/voice-comparator/assign-niche', ['niche' => 'gamer', 'voice_id' => 'echo']],
            'meta oauth start'   => ['GET', '/marketing/meta/oauth/start', []],
            'meta oauth callback'=> ['GET', '/marketing/meta/oauth/callback', []],
        ];
    }

    /** @dataProvider rutasConfigCompartida */
    public function test_con_empresa_b_activa_la_config_compartida_da_403_config_compartida(string $metodo, string $url, array $cuerpo): void
    {
        $settingsAntes = DB::table('marketing_settings')->orderBy('id')->get()->toArray();

        $r = $this->como($this->adminB, $this->empresaB->id)->json($metodo, $url, $cuerpo);
        $this->assertNegativa($r, 'config_compartida', self::MSG_CONFIG_COMPARTIDA);

        $this->assertEquals($settingsAntes, DB::table('marketing_settings')->orderBy('id')->get()->toArray(), 'no debe tocar marketing_settings');
    }

    public function test_marketing_de_b_tampoco_edita_la_config_compartida(): void
    {
        $u = $this->crearUsuario('qa_mkt_b', [$this->empresaB->id => 'marketing']);
        $this->assertNegativa(
            $this->como($u, $this->empresaB->id)->putJson('/api/marketing/brand-kit', []),
            'config_compartida',
            self::MSG_CONFIG_COMPARTIDA
        );
    }

    public function test_admin_de_a_y_b_con_b_activa_recibe_config_compartida_y_con_a_activa_no(): void
    {
        $u = $this->crearUsuario('qa_admin_ab', [1 => 'admin', $this->empresaB->id => 'admin']);

        $this->assertNegativa($this->como($u, $this->empresaB->id)->putJson('/api/marketing/brand-kit', []), 'config_compartida', self::MSG_CONFIG_COMPARTIDA);
        $this->assertPermitido($this->como($u, 1)->getJson('/api/marketing/brand-kit'), 'admin A lectura brand-kit');

        $r = $this->como($u, 1)->putJson('/api/marketing/brand-kit', []);
        $this->assertNotSame('config_compartida', $r->json('error'));
        $this->assertNotSame('sin_permiso_rol', $r->json('error'));
    }

    public function test_ventas_con_meganet_activa_no_edita_config_compartida(): void
    {
        $this->assertNegativa($this->como($this->ventasA)->putJson('/api/marketing/brand-kit', []), 'sin_permiso_rol', self::MSG_SIN_PERMISO_ROL);
    }

    public function test_config_compartida_pagina_da_403(): void
    {
        $this->como($this->adminB, $this->empresaB->id)->get('/marketing/meta/oauth/start')->assertStatus(403);
    }

    // ── Nuevas: Empresas y equipo ────────────────────────────────────────────

    public function test_empresas_solo_admin_con_permiso_spatie(): void
    {
        $this->como($this->adminA)->getJson('/api/marketing/empresas')->assertOk();

        $this->assertNegativa($this->como($this->marketingA)->getJson('/api/marketing/empresas'), 'sin_permiso_rol', self::MSG_SIN_PERMISO_ROL);
        $this->assertNegativa($this->como($this->ventasA)->getJson('/api/marketing/empresas'), 'sin_permiso_rol', self::MSG_SIN_PERMISO_ROL);
        $this->assertNegativa($this->como($this->sinEmpresa)->getJson('/api/marketing/empresas'), 'sin_empresa', self::MSG_SIN_EMPRESA);

        // Admin de la empresa pero sin el permiso Spatie → 403 (formato Spatie; solo se verifica el status).
        $this->como($this->adminASinPermiso)->getJson('/api/marketing/empresas')->assertStatus(403);
        $this->como($this->adminASinPermiso)->postJson('/api/marketing/empresas', ['nombre' => 'No debería'])->assertStatus(403);
        $this->assertSame(0, DB::table('marketing_empresas')->where('nombre', 'No debería')->count());
    }

    public function test_empresas_y_equipo_pagina_403_a_marketing_ventas_y_sin_empresa(): void
    {
        $this->como($this->marketingA)->get('/marketing/empresas')->assertStatus(403);
        $this->como($this->ventasA)->get('/marketing/empresas')->assertStatus(403);
        $this->como($this->sinEmpresa)->get('/marketing/empresas')->assertStatus(403);
    }

    public function test_marketing_puede_todo_lo_de_marketing_menos_empresas(): void
    {
        $this->assertPermitido($this->como($this->marketingA)->getJson('/api/marketing/leads'), 'marketing leads');
        $this->assertPermitido($this->como($this->marketingA)->getJson('/api/marketing/multivariant-campaigns'), 'marketing multivariante');
        $this->assertPermitido($this->como($this->marketingA)->getJson('/api/marketing/brand-kit'), 'marketing brand-kit');
        $this->assertPermitido($this->como($this->marketingA)->getJson('/api/marketing/contexto'), 'marketing contexto');

        $r = $this->como($this->marketingA)->putJson('/api/marketing/brand-kit', []);
        $this->assertNotSame('config_compartida', $r->json('error'), 'marketing con Meganet activa sí edita la config compartida');
        $this->assertNotSame('sin_permiso_rol', $r->json('error'));
    }

    public function test_contexto_responde_a_los_tres_roles(): void
    {
        foreach ([$this->adminA, $this->marketingA, $this->ventasA, $this->adminB] as $u) {
            $this->como($u)->getJson('/api/marketing/contexto')->assertOk()->assertJsonPath('ok', true);
        }
    }

    public function test_quitar_la_asignacion_corta_el_acceso_en_la_siguiente_peticion(): void
    {
        $this->como($this->ventasA)->getJson('/api/marketing/leads')->assertOk();

        DB::table('marketing_empresa_usuario')->where('user_id', $this->ventasA->id)->update(['activo' => 0]);

        $this->assertNegativa($this->como($this->ventasA)->getJson('/api/marketing/leads'), 'sin_empresa', self::MSG_SIN_EMPRESA);
    }

    public function test_bajar_de_rol_aplica_en_la_siguiente_peticion(): void
    {
        $this->como($this->marketingA)->getJson('/api/marketing/multivariant-campaigns')->assertOk();

        DB::table('marketing_empresa_usuario')->where('user_id', $this->marketingA->id)->update(['rol' => 'ventas']);

        $this->assertNegativa($this->como($this->marketingA)->getJson('/api/marketing/multivariant-campaigns'), 'sin_permiso_rol', self::MSG_SIN_PERMISO_ROL);
    }
}
