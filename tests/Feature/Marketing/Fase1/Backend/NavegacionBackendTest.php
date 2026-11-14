<?php

namespace Tests\Feature\Marketing\Fase1\Backend;

use App\Modules\Addons\Marketing\Controllers\NavegacionController;
use Tests\Feature\Marketing\Fase1\Backend\Concerns\EscenarioBackendFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/** GET /api/marketing/navegacion (§2.4.3), con la regla B de Henry (solo admins de Meganet entran). */
class NavegacionBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase1;

    /** Permisos que pide el menú y que el módulo declaraba antes de la Fase 1. */
    private const PERMISOS_DE_MODULO = [
        'view-marketing-campaigns', 'view-video-templates', 'generate-video-content', 'view-video-content',
        'test-voices', 'manage-integrations',
        'view-publishing-dashboard', // Fase 2: «Publicación» ahora pide el permiso de su pantalla
    ];

    public function test_admin_con_todos_los_permisos_ve_todo_en_el_orden_de_module_json(): void
    {
        $admin = $this->adminMeganet(['marketing.leads.view', 'marketing.forms.view', 'marketing.brand_kit.configure']);
        $this->darPermisosDeModulo($admin, self::PERMISOS_DE_MODULO);

        $r = $this->actingAs($admin)->getJson('/api/marketing/navegacion')->assertOk()->json();

        $this->assertTrue($r['ok']);
        $this->assertSame('admin', $r['rol']);
        $this->assertSame(array_column(NavegacionController::menu(), 'url'), array_column($r['items'], 'url'));
        $this->assertSame(
            ['panel', 'campanas-360', 'reels-y-videos', 'publicacion', 'leads', 'pruebas-ab', 'configuracion'], // Fase 2: §2.11
            array_column($r['items'], 'clave')
        );
        $this->assertNotContains('/marketing/empresas', array_column($r['items'], 'url'), '«Empresas y equipo» se oculta (cambio de alcance A).');

        $video = $r['items'][2];
        $this->assertSame(['clave' => 'reels-y-videos', 'etiqueta' => 'Reels y videos', 'url' => '/marketing/video-templates', 'icono' => 'movie'], array_diff_key($video, ['sub' => 1]));
        $this->assertSame(['reels-y-videos-plantillas', 'reels-y-videos-generar', 'reels-y-videos-video-por-nicho', 'reels-y-videos-fila-de-produccion'], array_column($video['sub'], 'clave'));
        $this->assertSame(['/marketing/publishing', '/marketing/publishing/campaign', '/marketing/publishing/queue'], array_column($r['items'][3]['sub'], 'url'));
        $this->assertSame(['/marketing/brand-kit', '/marketing/voice-comparator', '/marketing/publishing/setup', '/integraciones'], array_column($r['items'][6]['sub'], 'url'));
    }

    public function test_solo_devuelve_campos_de_navegacion(): void
    {
        $r = $this->actingAs($this->adminMeganet())->getJson('/api/marketing/navegacion')->assertOk()->json();

        $this->assertSame(['ok', 'rol', 'items'], array_keys($r));
        foreach ($r['items'] as $item) {
            $this->assertSame(['clave', 'etiqueta', 'url', 'icono', 'sub'], array_keys($item));
            foreach ($item['sub'] as $sub) {
                $this->assertSame(['clave', 'etiqueta', 'url'], array_keys($sub));
            }
        }
        $this->assertStringNotContainsString('permission', json_encode($r));
        $this->assertStringNotContainsString('roles_empresa', json_encode($r));
    }

    public function test_sin_permisos_spatie_solo_ve_lo_que_no_pide_permiso(): void
    {
        $r = $this->actingAs($this->adminMeganet())->getJson('/api/marketing/navegacion')->assertOk()->json();

        // Fase 2 (§2.11): Conversaciones es pestaña de Leads y Publicación pide su permiso (más estricto que antes).
        $this->assertSame(['panel', 'campanas-360', 'pruebas-ab'], array_column($r['items'], 'clave'));
    }

    public function test_los_sub_se_filtran_por_su_permiso(): void
    {
        $admin = $this->adminMeganet();
        $this->darPermisosDeModulo($admin, ['view-video-templates', 'view-video-content']);

        $r = $this->actingAs($admin)->getJson('/api/marketing/navegacion')->assertOk()->json();
        $video = collect($r['items'])->firstWhere('clave', 'reels-y-videos');

        $this->assertSame(['reels-y-videos-plantillas', 'reels-y-videos-fila-de-produccion'], array_column($video['sub'], 'clave'));
    }

    public function test_quien_no_es_admin_de_meganet_recibe_403(): void
    {
        $sinEmpresa = $this->crearUsuario(['super-administrator']);
        $this->actingAs($sinEmpresa)->getJson('/api/marketing/navegacion')->assertForbidden()->assertJsonPath('error', 'sin_empresa');

        $ventas = $this->crearUsuario();
        $this->asignar($ventas, 1, 'ventas');
        $this->actingAs($ventas)->getJson('/api/marketing/navegacion')->assertForbidden()->assertJsonPath('error', 'sin_acceso');
    }
}
