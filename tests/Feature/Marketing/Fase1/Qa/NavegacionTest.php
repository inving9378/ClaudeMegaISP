<?php

namespace Tests\Feature\Marketing\Fase1\Qa;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase1\Qa\Concerns\EscenarioFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 1 — `GET /api/marketing/navegacion` (contrato §2.4.3, §2.8, criterio 9; cambio A/B).
 * Orden = module.json → menu[0].children; filtro por rol de empresa y permiso; sin campos sensibles;
 * «Empresas y equipo» fuera del menú (cambio A); con la regla B solo el admin de la empresa 1 la obtiene.
 */
class NavegacionTest extends MarketingTestCase
{
    use EscenarioFase1;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
        $this->montarEscenarioFase1();
        // Fase 2 (§2.11): «Publicación» ahora pide el permiso de su pantalla; el admin de prueba lo recibe (solo se agrega).
        $this->adminA->givePermissionTo('view-publishing-dashboard');
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function menu(): array
    {
        return json_decode((string) file_get_contents(base_path('app/Modules/Addons/Marketing/module.json')), true)['menu'][0]['children'];
    }

    public function test_admin_recibe_los_items_en_el_orden_de_module_json(): void
    {
        $r = $this->como($this->adminA, 1)->getJson('/api/marketing/navegacion')->assertOk();
        $r->assertJsonPath('ok', true)->assertJsonPath('rol', 'admin');

        $urlsMenu = array_column($this->menu(), 'url');
        $urls = array_column($r->json('items'), 'url');
        $this->assertSame($urlsMenu, $urls, 'super-administrador admin de la 1 ve todo y en el orden de module.json');
        $this->assertSame(array_column($this->menu(), 'label'), array_column($r->json('items'), 'etiqueta'));
        $this->assertSame('/marketing', $urls[0]);
    }

    public function test_orden_de_module_json_es_el_del_contrato_sin_empresas_y_equipo(): void
    {
        $this->assertSame(
            // Fase 2: menú aprobado por Henry (§2.11 de fase-2-paquetes.md).
            ['/marketing', '/marketing/campanas-360', '/marketing/video-templates', '/marketing/publishing', '/marketing/leads',
             '/marketing/pilot-campaigns', '/marketing/brand-kit'],
            array_column($this->menu(), 'url'),
            '§2.8 menos «Empresas y equipo» (cambio de alcance A: se oculta)'
        );
        foreach ($this->menu() as $item) {
            $this->assertArrayHasKey('icono_material', $item, $item['label']);
            $this->assertArrayHasKey('roles_empresa', $item, $item['label']);
        }
    }

    public function test_solo_devuelve_campos_publicos(): void
    {
        $r = $this->como($this->adminA, 1)->getJson('/api/marketing/navegacion')->assertOk();
        $this->assertSame(['ok', 'rol', 'items'], array_keys($r->json()));
        foreach ($r->json('items') as $item) {
            $this->assertSame(['clave', 'etiqueta', 'url', 'icono', 'sub'], array_keys($item));
            foreach ($item['sub'] as $sub) {
                $this->assertSame(['clave', 'etiqueta', 'url'], array_keys($sub));
            }
        }
        $raw = (string) $r->getContent();
        foreach (['permission', 'roles_empresa', 'login_user', 'email', 'password', $this->adminA->login_user] as $sensible) {
            $this->assertStringNotContainsString($sensible, $raw);
        }
    }

    public function test_subitems_de_video_y_publicacion(): void
    {
        $items = collect($this->como($this->adminA, 1)->getJson('/api/marketing/navegacion')->json('items'))->keyBy('url');
        // Fase 2 (§2.11): Voces y Canales pasan a Configuración; Video por nicho entra a Reels y videos.
        $this->assertSame(['/marketing/video-templates', '/marketing/video-generator', '/marketing/campaigns/generate', '/marketing/video-queue'],
            array_column($items['/marketing/video-templates']['sub'], 'url'));
        $this->assertSame(['/marketing/publishing', '/marketing/publishing/campaign', '/marketing/publishing/queue'],
            array_column($items['/marketing/publishing']['sub'], 'url'));
        $this->assertSame(['/marketing/leads', '/marketing/conversations', '/marketing/lead-forms'],
            array_column($items['/marketing/leads']['sub'], 'url'));
        $this->assertSame(['/marketing/brand-kit', '/marketing/voice-comparator', '/marketing/publishing/setup', '/integraciones'],
            array_column($items['/marketing/brand-kit']['sub'], 'url'));
    }

    public function test_admin_sin_un_permiso_no_ve_ese_item(): void
    {
        // Admin de la empresa 1 SIN roles de sistema: solo con algunos permisos directos.
        $u = $this->crearUsuario('qa1_nav_admin_parcial', [1 => 'admin'], [], ['marketing.leads.view']);
        // Fase 2 (§2.11): las pantallas pueden quedar como pestaña de una sección; se revisan secciones Y pestañas.
        $items = $this->como($u, 1)->getJson('/api/marketing/navegacion')->assertOk()->json('items');
        $urls = array_values(array_unique(array_merge(array_column($items, 'url'), ...array_map(fn ($i) => array_column($i['sub'], 'url'), $items))));
        $this->assertContains('/marketing/leads', $urls);
        $this->assertContains('/marketing', $urls, 'Panel no pide permiso');
        $this->assertContains('/marketing/conversations', $urls, 'Conversaciones no pide permiso');
        foreach (['/marketing/lead-forms', '/marketing/brand-kit', '/marketing/video-templates', '/marketing/campaigns/generate', '/integraciones'] as $oculto) {
            $this->assertNotContains($oculto, $urls, "{$oculto} pide un permiso que no tiene");
        }
        $this->assertNotContains('/marketing/empresas', $urls);
    }

    public function test_regla_b_nadie_mas_obtiene_la_navegacion(): void
    {
        $this->como($this->marketingA, 1)->getJson('/api/marketing/navegacion')->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
        $this->como($this->ventasA, 1)->getJson('/api/marketing/navegacion')->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
        $this->como($this->sinEmpresa)->getJson('/api/marketing/navegacion')->assertStatus(403)->assertJsonPath('error', 'sin_empresa');
        $this->anonimo()->getJson('/api/marketing/navegacion')->assertStatus(401);
    }
}
