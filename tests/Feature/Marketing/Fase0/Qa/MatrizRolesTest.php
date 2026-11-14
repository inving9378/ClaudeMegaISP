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
 * AJUSTE FASE 1 (QA, cambio de alcance B de Henry, 2026-11-13): solo entra quien tiene fila ACTIVA con
 * rol `admin` en la empresa 1 (y usuario activo); la empresa activa SIEMPRE es la 1. Las filas `marketing`
 * y `ventas` y los admins de otra empresa reciben 403 `sin_acceso`. Cada prueba cambiada lleva
 * «[Fase 1 · B]» con su razón; las que comprobaban que ventas/marketing SÍ entraban ahora comprueban que
 * NO entran (más estricto). Las de `config_compartida` ya no se pueden alcanzar (nadie de otra empresa
 * entra): se conservan como «otra empresa → sin_acceso y no toca la config».
 *
 * Nota: las pruebas de página dependen de que el layout `core-layout::master` renderice en el
 * worktree (necesita `public/js/app.js` compilado por frontend, §2.10.1). Las de JSON no.
 */
class MatrizRolesTest extends MarketingTestCase
{
    use EscenarioEmpresas;

    private const MSG_SIN_EMPRESA = 'No tienes acceso a Marketing. Pide acceso a un administrador de Marketing.'; // [Fase 1] texto nuevo (corrección 5 de QA)
    private const MSG_SIN_PERMISO_ROL = 'Tu rol en esta empresa no permite esta acción.';
    private const MSG_SIN_ACCESO = 'Marketing está abierto a super-administrador, Administrador, Desarrollador y Mostrador. Si necesitas entrar, pídele a un administrador que te asigne uno de esos roles.';
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
        $r->assertSee('No tienes acceso a Marketing');
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

    /** [Fase 1 · B] La fila en la 1 pasa de 'ventas' a 'admin' (ventas ya no entra); sigue cayendo a la 1. */
    public function test_sesion_apuntando_a_empresa_inactiva_cae_a_la_activa(): void
    {
        $inactiva = $this->crearEmpresa('QA Inactiva 2', activo: false);
        $u = $this->crearUsuario('qa_mixto', [1 => 'admin', $inactiva->id => 'admin']);

        $r = $this->como($u, $inactiva->id)->getJson('/api/marketing/contexto');
        $r->assertOk();
        $this->assertSame(1, $r->json('empresa_activa.id'));
        $this->assertSame('admin', $r->json('empresa_activa.rol'));
    }

    // ── V: ventas entra a leads y conversaciones (D2) ────────────────────────

    /** [Fase 1 · B] Antes ventas veía/creaba/editaba leads y conversaciones (D2). Ahora: 403 sin_acceso y nada cambia. */
    public function test_ventas_ve_crea_edita_e_historial_de_leads_y_conversaciones(): void
    {
        $lead = $this->a['lead'];
        $antes = $this->fila('marketing_leads', $lead->id);
        $cuantos = DB::table('marketing_leads')->count();
        foreach ([
            ['GET', '/api/marketing/leads', []], ['GET', "/api/marketing/leads/{$lead->id}", []],
            ['GET', "/api/marketing/leads/{$lead->id}/activities", []],
            ['PUT', "/api/marketing/leads/{$lead->id}", ['notes' => 'editado por ventas']],
            ['POST', '/api/marketing/leads', ['source_id' => $this->fuente->id, 'full_name' => 'Alta de ventas']],
            ['GET', '/api/marketing/conversations', []], ['GET', "/api/marketing/conversations/{$this->a['conversacion']->id}", []],
            ['GET', "/api/marketing/conversations/{$this->a['conversacion']->id}/messages", []], ['GET', '/api/marketing/lead-sources', []],
        ] as [$m, $url, $datos]) {
            $this->assertNegativa($this->como($this->ventasA)->json($m, $url, $datos), 'sin_acceso', self::MSG_SIN_ACCESO);
        }
        $this->assertEquals($antes, $this->fila('marketing_leads', $lead->id));
        $this->assertSame($cuantos, DB::table('marketing_leads')->count());
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

    /**
     * [Fase 1 · B] Antes `sin_permiso_rol` (marketing.rol); ahora corta antes marketing.empresa con `sin_acceso`.
     * @dataProvider rutasSoloMarketing
     */
    public function test_ventas_recibe_sin_permiso_rol_en_rutas_de_marketing(string $metodo, string $plantilla): void
    {
        $url = str_replace(['{lead}', '{campana}'], [$this->a['lead']->id, $this->a['campana']->id], $plantilla);
        $antes = $this->fila('marketing_leads', $this->a['lead']->id);

        $r = $this->como($this->ventasA)->json($metodo, $url, ['user_id' => $this->ventasA->id]);
        $this->assertNegativa($r, 'sin_acceso', self::MSG_SIN_ACCESO);

        $this->assertEquals($antes, $this->fila('marketing_leads', $this->a['lead']->id), 'el lead no debe cambiar');
        Queue::assertNothingPushed();
    }

    /**
     * [Fase 1 · B] Antes marketing SÍ entraba (solo lectura). Ahora NO entra, ni lectura ni escritura; y el admin de la 1 sí.
     * @dataProvider rutasSoloMarketing
     */
    public function test_marketing_si_entra_a_rutas_de_marketing(string $metodo, string $plantilla): void
    {
        $url = str_replace(['{lead}', '{campana}'], [$this->a['lead']->id, $this->a['campana']->id], $plantilla);
        $antes = $this->fila('marketing_leads', $this->a['lead']->id);
        $this->assertNegativa($this->como($this->marketingA)->json($metodo, $url, ['user_id' => $this->marketingA->id]), 'sin_acceso', self::MSG_SIN_ACCESO);
        $this->assertEquals($antes, $this->fila('marketing_leads', $this->a['lead']->id));
        if ($metodo === 'GET') {
            $this->assertPermitido($this->como($this->adminA)->getJson($url), "admin de la 1 GET {$url}");
        }
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

    /** [Fase 1 · B] Antes ventas abría Leads y Conversaciones; ahora 403 (página de sin acceso). */
    public function test_ventas_paginas_de_leads_y_conversaciones_no_dan_403(): void
    {
        foreach (['/marketing/leads', "/marketing/leads/{$this->a['lead']->id}", '/marketing/conversations'] as $url) {
            $this->como($this->ventasA)->get($url)->assertStatus(403);
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

    /**
     * [Fase 1 · B] Antes `config_compartida`; con la regla B el admin de otra empresa no entra: `sin_acceso`.
     * Se conserva lo importante: 403 y marketing_settings intacto.
     * @dataProvider rutasConfigCompartida
     */
    public function test_con_empresa_b_activa_la_config_compartida_da_403_config_compartida(string $metodo, string $url, array $cuerpo): void
    {
        $settingsAntes = DB::table('marketing_settings')->orderBy('id')->get()->toArray();

        $r = $this->como($this->adminB, $this->empresaB->id)->json($metodo, $url, $cuerpo);
        $this->assertNegativa($r, 'sin_acceso', self::MSG_SIN_ACCESO);

        $this->assertEquals($settingsAntes, DB::table('marketing_settings')->orderBy('id')->get()->toArray(), 'no debe tocar marketing_settings');
    }

    /** [Fase 1 · B] Antes `config_compartida`; ahora `sin_acceso`. */
    public function test_marketing_de_b_tampoco_edita_la_config_compartida(): void
    {
        $u = $this->crearUsuario('qa_mkt_b', [$this->empresaB->id => 'marketing']);
        $this->assertNegativa($this->como($u, $this->empresaB->id)->putJson('/api/marketing/brand-kit', []), 'sin_acceso', self::MSG_SIN_ACCESO);
    }

    /**
     * [Fase 1 · A/B] Antes, con B elegida en sesión, `config_compartida`. Ahora la empresa activa SIEMPRE es la 1:
     * pedir B en sesión no cambia nada y el admin de la 1 pasa la capa en ambos casos.
     */
    public function test_admin_de_a_y_b_con_b_activa_recibe_config_compartida_y_con_a_activa_no(): void
    {
        $u = $this->crearUsuario('qa_admin_ab', [1 => 'admin', $this->empresaB->id => 'admin']);

        foreach ([$this->empresaB->id, 1] as $enSesion) {
            $this->assertPermitido($this->como($u, $enSesion)->getJson('/api/marketing/brand-kit'), "admin lectura brand-kit (sesión {$enSesion})");
            $this->como($u, $enSesion)->getJson('/api/marketing/contexto')->assertJsonPath('empresa_activa.id', 1);
            $r = $this->como($u, $enSesion)->putJson('/api/marketing/brand-kit', []);
            $this->assertNotSame('config_compartida', $r->json('error'));
            $this->assertNotSame('sin_acceso', $r->json('error'));
        }
    }

    /** [Fase 1 · B] Antes `sin_permiso_rol`; ahora `sin_acceso`. */
    public function test_ventas_con_meganet_activa_no_edita_config_compartida(): void
    {
        $this->assertNegativa($this->como($this->ventasA)->putJson('/api/marketing/brand-kit', []), 'sin_acceso', self::MSG_SIN_ACCESO);
    }

    public function test_config_compartida_pagina_da_403(): void
    {
        $this->como($this->adminB, $this->empresaB->id)->get('/marketing/meta/oauth/start')->assertStatus(403);
    }

    // ── Nuevas: Empresas y equipo ────────────────────────────────────────────

    public function test_empresas_solo_admin_con_permiso_spatie(): void
    {
        $this->como($this->adminA)->getJson('/api/marketing/empresas')->assertOk();

        // [Fase 1 · B] marketing y ventas: antes `sin_permiso_rol`, ahora `sin_acceso`.
        $this->assertNegativa($this->como($this->marketingA)->getJson('/api/marketing/empresas'), 'sin_acceso', self::MSG_SIN_ACCESO);
        $this->assertNegativa($this->como($this->ventasA)->getJson('/api/marketing/empresas'), 'sin_acceso', self::MSG_SIN_ACCESO);
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

    /** [Fase 1 · B] Antes marketing podía todo menos Empresas; ahora no entra a nada. El admin de la 1 sí. */
    public function test_marketing_puede_todo_lo_de_marketing_menos_empresas(): void
    {
        foreach (['/api/marketing/leads', '/api/marketing/multivariant-campaigns', '/api/marketing/brand-kit', '/api/marketing/contexto'] as $url) {
            $this->assertNegativa($this->como($this->marketingA)->getJson($url), 'sin_acceso', self::MSG_SIN_ACCESO);
            $this->assertPermitido($this->como($this->adminA)->getJson($url), "admin {$url}");
        }
        $this->assertNegativa($this->como($this->marketingA)->putJson('/api/marketing/brand-kit', []), 'sin_acceso', self::MSG_SIN_ACCESO);
    }

    /** [Fase 1 · B] Antes el contexto respondía a los tres roles y al admin de B; ahora solo al admin de la 1. */
    public function test_contexto_responde_a_los_tres_roles(): void
    {
        $this->como($this->adminA)->getJson('/api/marketing/contexto')->assertOk()->assertJsonPath('ok', true);
        foreach ([$this->marketingA, $this->ventasA, $this->adminB] as $u) {
            $this->assertNegativa($this->como($u)->getJson('/api/marketing/contexto'), 'sin_acceso', self::MSG_SIN_ACCESO);
        }
    }

    /** [Fase 1 · B] Antes con ventasA (que ya no entra); ahora con el admin de la 1. Misma exigencia. */
    public function test_quitar_la_asignacion_corta_el_acceso_en_la_siguiente_peticion(): void
    {
        $this->como($this->adminA)->getJson('/api/marketing/leads')->assertOk();

        DB::table('marketing_empresa_usuario')->where('user_id', $this->adminA->id)->update(['activo' => 0]);

        $this->assertNegativa($this->como($this->adminA)->getJson('/api/marketing/leads'), 'sin_empresa', self::MSG_SIN_EMPRESA);
    }

    /** [Fase 1 · B] Antes marketing → ventas daba `sin_permiso_rol`; ahora admin → marketing da `sin_acceso`. */
    public function test_bajar_de_rol_aplica_en_la_siguiente_peticion(): void
    {
        $this->como($this->adminA)->getJson('/api/marketing/multivariant-campaigns')->assertOk();

        DB::table('marketing_empresa_usuario')->where('user_id', $this->adminA->id)->update(['rol' => 'marketing']);

        $this->assertNegativa($this->como($this->adminA)->getJson('/api/marketing/multivariant-campaigns'), 'sin_acceso', self::MSG_SIN_ACCESO);
    }
}
