<?php

namespace Tests\Feature\Marketing\Fase1\Qa;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase1\Qa\Concerns\EscenarioFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 1 — [F1-1] acceso sin `config/route_permission.php` (contrato §2.6, criterio 8),
 * con la regla B del cambio de alcance: una persona SIN roles de administración del sistema, con fila
 * `admin` en la empresa 1 y los permisos Spatie de cada acción, abre las pantallas y APIs (antes
 * `check_route_permission` la mandaba fuera). Fila marketing/ventas, sin empresa e inactivo: fuera.
 * El recorrido ruta por ruta está en AccesoNegadoPorDefectoTest; aquí van los casos de usuario.
 */
class AccesoSinRutaPermisoTest extends MarketingTestCase
{
    use EscenarioFase1;

    private const PANTALLAS = [
        '/marketing' => 'marketing-dashboard', '/marketing/leads' => null, '/marketing/lead-forms' => null,
        '/marketing/conversations' => null, '/marketing/video-templates' => null, '/marketing/video-generator' => null,
        '/marketing/video-queue' => null, '/marketing/brand-kit' => null, '/marketing/campaigns/generate' => null,
        '/marketing/voice-comparator' => null, '/marketing/pilot-campaigns' => null, '/marketing/publishing' => null,
        '/marketing/publishing/setup' => null, '/marketing/publishing/queue' => null, '/marketing/publishing/campaign' => null,
    ];

    private \App\Models\User $adminSinRol;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
        $this->montarEscenarioFase1();
        // Como `mkt_prev_marketing_sinrol` de §2.10, pero con rol admin (regla B): sin roles de sistema.
        $this->adminSinRol = $this->crearUsuario('qa1_admin_sinrol', [1 => 'admin'], [], array_merge(
            ['marketing.leads.view', 'marketing.forms.view', 'marketing.brand_kit.configure'],
            ['view-video-templates', 'view-video-content', 'view-marketing-campaigns']
        ));
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    public function test_el_usuario_no_tiene_ningun_rol_de_sistema(): void
    {
        $this->assertSame([], $this->adminSinRol->getRoleNames()->all());
        $this->assertFalse($this->adminSinRol->isAdmin());
        $this->assertFalse($this->adminSinRol->isSuperAdmin());
    }

    public function test_admin_sin_rol_de_sistema_abre_todas_las_pantallas(): void
    {
        $fallas = [];
        foreach (array_keys(self::PANTALLAS) as $url) {
            $r = $this->como($this->adminSinRol, 1)->get($url, ['Accept' => 'text/html']);
            if ($r->status() !== 200) {
                $fallas[] = "{$url} → {$r->status()}";
            }
        }
        $this->assertSame([], $fallas, 'Sin check_route_permission, el admin de la empresa 1 sin rol de sistema debe abrirlas');
    }

    public function test_admin_sin_rol_de_sistema_usa_las_apis_de_sus_permisos(): void
    {
        foreach (['/api/marketing/leads', '/api/marketing/lead-forms', '/api/marketing/brand-kit', '/api/marketing/conversations',
                  '/api/marketing/video-templates', '/api/marketing/generated-content', '/api/marketing/multivariant-campaigns',
                  '/api/marketing/publishing/channels', '/api/marketing/publishing/publications', '/api/marketing/dashboard/resumen',
                  '/api/marketing/dashboard/campanas-activas', '/api/marketing/navegacion', '/api/marketing/contexto'] as $url) {
            $this->como($this->adminSinRol, 1)->getJson($url)->assertOk();
        }
    }

    public function test_las_acciones_siguen_pidiendo_su_permiso_spatie(): void
    {
        // Entrar no da todas las facultades: lo que no tiene, Spatie lo niega (403 que NO es de la capa por empresa).
        $sinLeads = $this->crearUsuario('qa1_admin_sin_leads', [1 => 'admin'], [], ['marketing.forms.view']);
        $r = $this->como($sinLeads, 1)->getJson('/api/marketing/leads');
        $r->assertStatus(403);
        $this->assertNotContains($r->json('error'), ['sin_empresa', 'sin_acceso', 'usuario_inactivo']);
        $this->como($sinLeads, 1)->postJson('/api/marketing/leads', ['full_name' => 'x', 'source_id' => $this->fuente->id])->assertStatus(403);
        $this->como($sinLeads, 1)->getJson('/api/marketing/lead-forms')->assertOk();
    }

    public function test_fila_marketing_o_ventas_no_entra_a_ninguna_pantalla(): void
    {
        foreach ([$this->marketingA, $this->ventasA] as $u) {
            foreach (['/marketing', '/marketing/leads', '/marketing/conversations', '/marketing/video-templates', '/marketing/publishing'] as $url) {
                $this->como($u, 1)->get($url, ['Accept' => 'text/html'])->assertStatus(403);
            }
            foreach (['/api/marketing/dashboard/resumen', '/api/marketing/leads', '/api/marketing/conversations',
                      '/api/marketing/video-templates', '/api/marketing/publishing/channels'] as $api) {
                $this->como($u, 1)->getJson($api)->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
            }
        }
    }

    public function test_sin_empresa_ve_aun_no_tienes_empresa(): void
    {
        $this->como($this->sinEmpresa)->get('/marketing/leads', ['Accept' => 'text/html'])
            ->assertStatus(403)->assertSee('No tienes acceso a Marketing');
        $this->como($this->sinEmpresa)->getJson('/api/marketing/leads')->assertStatus(403)->assertJsonPath('error', 'sin_empresa');
    }

    public function test_inactivo_es_rechazado_y_su_sesion_se_cierra(): void
    {
        $this->como($this->inactivo, 1)->getJson('/api/marketing/leads')
            ->assertStatus(401)->assertExactJson(['ok' => false, 'error' => 'usuario_inactivo', 'mensaje' => 'Tu usuario está inactivo.']);
        $this->assertGuest();
        $this->como($this->inactivo, 1)->get('/marketing/leads', ['Accept' => 'text/html'])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_un_usuario_que_se_desactiva_a_media_sesion_queda_fuera(): void
    {
        $this->como($this->adminSinRol, 1)->getJson('/api/marketing/leads')->assertOk();
        $this->adminSinRol->forceFill(['estado' => 'inactivo'])->save();
        $this->actingAs($this->adminSinRol->fresh());
        $this->contexto()->limpiar();
        $this->getJson('/api/marketing/leads')->assertStatus(401)->assertJsonPath('error', 'usuario_inactivo');
    }
}
