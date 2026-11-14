<?php

namespace Tests\Feature\Marketing\Fase1\Frontend;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 1 · paquete frontend: cada vista Blade de Marketing monta su componente (tag kebab-case) para el
 * administrador de la empresa 1; las vistas «M» no se abren al rol `ventas`; sin empresa → pantalla explicativa
 * con 403; sin sesión nunca 200; y `/marketing/campaigns/{id}` (legado retirado, F1-3) redirige a `/marketing`.
 *
 * No siembra permisos: el usuario recibe el rol `super-administrator`, que ya existe en megaisp_test.
 * Cambio de alcance (Henry, 2026-11-13): solo Meganet → solo empresa 1; «Empresas y equipo» queda fuera
 * (la pantalla existe pero no se ofrece).
 */
class PantallasMarketingTest extends MarketingTestCase
{
    /** URL → tag del componente que debe montar. */
    private const PANTALLAS = [
        '/marketing'                     => 'marketing-dashboard',
        '/marketing/campaigns'           => 'marketing-dashboard',
        '/marketing/leads'               => 'marketing-leads-view',
        '/marketing/lead-forms'          => 'marketing-lead-forms-view',
        '/marketing/lead-forms/create'   => 'marketing-lead-form-editor',
        '/marketing/conversations'       => 'marketing-conversations-view',
        '/marketing/video-templates'     => 'marketing-video-templates-view',
        '/marketing/video-generator'     => 'marketing-video-generator-view',
        '/marketing/video-queue'         => 'marketing-video-queue-view',
        '/marketing/brand-kit'           => 'marketing-brand-kit-view',
        '/marketing/campaigns/generate'  => 'marketing-campaign-generator-view',
        '/marketing/voice-comparator'    => 'voice-comparator-view',
        '/marketing/pilot-campaigns'     => 'marketing-pilot-campaigns-view',
        '/marketing/publishing'          => 'marketing-publishing-dashboard-view',
        '/marketing/publishing/campaign' => 'marketing-publish-campaign-view',
        '/marketing/publishing/queue'    => 'marketing-publication-queue-view',
        '/marketing/publishing/setup'    => 'marketing-channels-setup-view',
    ];

    /** Pantallas con id en la URL (el Blade monta el componente; el 404 lo resuelve la API y la UI lo muestra). */
    private const PANTALLAS_CON_ID = [
        '/marketing/leads/%d'          => 'marketing-lead-detail',
        '/marketing/lead-forms/%d/edit' => 'marketing-lead-form-editor',
        '/marketing/conversations/%d'  => 'marketing-conversations-view',
    ];

    /** Vistas que exigen rol admin/marketing (grupo $M de routes.php): `ventas` nunca las abre. */
    private const PANTALLAS_M = [
        '/marketing', '/marketing/lead-forms', '/marketing/video-templates', '/marketing/video-generator',
        '/marketing/video-queue', '/marketing/brand-kit', '/marketing/campaigns/generate', '/marketing/voice-comparator',
        '/marketing/pilot-campaigns', '/marketing/publishing',
    ];

    private function usuario(?string $rolEmpresa): User
    {
        $u = User::factory()->create([
            'login_user' => 'mkt_f1_front_' . Str::lower(Str::random(10)),
            'estado'     => 'activo',
            'is_system'  => 0,
        ]);
        $u->assignRole('super-administrator');
        if ($rolEmpresa) {
            DB::table('marketing_empresa_usuario')->updateOrInsert(
                ['company_id' => 1, 'user_id' => $u->id],
                ['rol' => $rolEmpresa, 'activo' => 1, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]
            );
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    private function como(User $u)
    {
        return $this->actingAs($u)->withSession(['marketing.empresa_activa_id' => 1]);
    }

    public function test_el_admin_abre_cada_pantalla_con_su_componente(): void
    {
        $admin = $this->usuario('admin');

        foreach (self::PANTALLAS as $url => $tag) {
            $this->como($admin)->get($url)
                ->assertOk()
                ->assertSee('<' . $tag, false);
        }
    }

    public function test_las_pantallas_con_id_montan_su_componente(): void
    {
        $admin = $this->usuario('admin');

        foreach (self::PANTALLAS_CON_ID as $patron => $tag) {
            $this->como($admin)->get(sprintf($patron, 999999))
                ->assertOk()
                ->assertSee('<' . $tag, false);
        }
    }

    public function test_el_panel_ya_no_monta_las_pantallas_legado(): void
    {
        $admin = $this->usuario('admin');

        $this->como($admin)->get('/marketing')
            ->assertOk()
            ->assertDontSee('<marketing-campaigns', false)
            ->assertDontSee('<marketing-leads>', false)
            ->assertDontSee('<marketing-content-generator', false);
    }

    public function test_ventas_no_abre_las_pantallas_de_marketing(): void
    {
        $ventas = $this->usuario('ventas');

        foreach (self::PANTALLAS_M as $url) {
            $this->como($ventas)->get($url)->assertForbidden();
        }
    }

    public function test_sin_empresa_ve_la_pantalla_explicativa_con_403(): void
    {
        $sinEmpresa = $this->usuario(null);

        foreach (['/marketing', '/marketing/leads', '/marketing/brand-kit'] as $url) {
            $this->actingAs($sinEmpresa)->get($url)
                ->assertForbidden()
                ->assertSee('No tienes acceso a Marketing')
                ->assertDontSee('<marketing-', false);
        }
    }

    public function test_sin_sesion_nunca_responde_200(): void
    {
        foreach (array_keys(self::PANTALLAS) as $url) {
            $status = $this->get($url)->getStatusCode();
            $this->assertNotSame(200, $status, "{$url} respondió 200 sin sesión");
            $this->assertContains($status, [302, 401, 419], "{$url} respondió {$status} sin sesión");
        }
    }

    public function test_la_ficha_de_campana_legado_redirige_al_panel(): void
    {
        $admin = $this->usuario('admin');
        $id = DB::table('marketing_campaigns')->insertGetId([
            'company_id' => 1, 'name' => 'F1 front prueba', 'slug' => 'f1-front-' . Str::lower(Str::random(8)),
            'type' => 'manual', 'budget' => '0.00', 'spent' => '0.00', 'currency' => 'MXN',
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->como($admin)->get("/marketing/campaigns/{$id}")->assertRedirect('/marketing');
    }
}
