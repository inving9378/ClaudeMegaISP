<?php

namespace Tests\Feature\Marketing\Fase2\Frontend;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 2 · paquete frontend (contrato §4): las 4 vistas Blade de Campañas 360 montan su componente con su prop
 * para el administrador de Meganet (fila `admin` activa en la empresa 1); 403 a quien no es admin de Meganet
 * (fila de otro rol, fila inactiva, super-administrador sin fila); sin sesión nunca 200 (redirige al login).
 *
 * No siembra permisos: el usuario recibe el rol `super-administrator`, que ya existe en megaisp_test.
 * La ficha y la edición se piden con un id inexistente: el Blade monta el componente y el 404 lo resuelve la API
 * (la pantalla muestra «No encontrado»).
 *
 * Menú (§2.11, aprobado por Henry): el submenú lateral de Marketing muestra al admin de Meganet exactamente 7
 * secciones en el orden aprobado, y nadie más ve «Marketing». En megaisp_test no existe la fila `marketing` de
 * `module_sidebar_config` (sí en dev/prod): la prueba la crea dentro de su transacción.
 */
class PantallasCampanas360Test extends MarketingTestCase
{
    private function usuario(?string $rolEmpresa, bool $activo = true): User
    {
        $u = User::factory()->create([
            'login_user' => 'mkt_f2_front_' . Str::lower(Str::random(10)),
            'estado'     => 'activo',
            'is_system'  => 0,
        ]);
        $u->assignRole('super-administrator');
        if ($rolEmpresa) {
            DB::table('marketing_empresa_usuario')->updateOrInsert(
                ['company_id' => 1, 'user_id' => $u->id],
                ['rol' => $rolEmpresa, 'activo' => $activo ? 1 : 0, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]
            );
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    private function como(User $u)
    {
        return $this->actingAs($u)->withSession(['marketing.empresa_activa_id' => 1]);
    }

    /** URL → [tag, fragmento de la prop que debe llevar el tag]. */
    private function pantallas(): array
    {
        return [
            '/marketing/campanas-360'                => ['marketing-campanas-360-lista', null],
            '/marketing/campanas-360/nueva'          => ['marketing-campana-360-asistente', ':campana-id="null"'],
            '/marketing/campanas-360/999999'         => ['marketing-campana-360-ficha', ':campana-id="999999"'],
            '/marketing/campanas-360/999999/editar'  => ['marketing-campana-360-asistente', ':campana-id="999999"'],
        ];
    }

    public function test_el_admin_de_meganet_abre_cada_pantalla_con_su_componente_y_su_prop(): void
    {
        $admin = $this->usuario('admin');

        foreach ($this->pantallas() as $url => [$tag, $prop]) {
            $r = $this->como($admin)->get($url);
            $r->assertOk();
            $r->assertSee('<' . $tag, false);
            if ($prop) {
                $r->assertSee($prop, false);
            }
        }
    }

    public function test_la_lista_no_monta_otras_pantallas(): void
    {
        $admin = $this->usuario('admin');

        $this->como($admin)->get('/marketing/campanas-360')
            ->assertOk()
            ->assertDontSee('<marketing-campana-360-ficha', false)
            ->assertDontSee('<marketing-campana-360-asistente', false);
    }

    public function test_quien_no_es_admin_de_meganet_recibe_403(): void
    {
        $casos = [
            'fila marketing' => $this->usuario('marketing'),
            'fila ventas'    => $this->usuario('ventas'),
            'fila inactiva'  => $this->usuario('admin', false),
            'sin fila'       => $this->usuario(null),
        ];

        foreach ($casos as $caso => $u) {
            foreach (array_keys($this->pantallas()) as $url) {
                $r = $this->actingAs($u)->withSession(['marketing.empresa_activa_id' => 1])->get($url);
                $this->assertSame(403, $r->getStatusCode(), "{$caso}: {$url} respondió {$r->getStatusCode()}");
                $r->assertDontSee('<marketing-campana', false);
            }
        }
    }

    public function test_sin_sesion_nunca_responde_200_y_lleva_al_login(): void
    {
        foreach (array_keys($this->pantallas()) as $url) {
            $r = $this->get($url);
            $this->assertNotSame(200, $r->getStatusCode(), "{$url} respondió 200 sin sesión");
            $this->assertContains($r->getStatusCode(), [302, 401, 419], "{$url} respondió {$r->getStatusCode()} sin sesión");
            if ($r->getStatusCode() === 302) {
                $this->assertStringContainsString('/login', (string) $r->headers->get('Location'), "{$url} no lleva al login");
            }
        }
    }

    /** Secciones del submenú lateral (data-mkt-item) en el orden en que aparecen en la página. */
    private function seccionesDelMenu(string $html): array
    {
        preg_match_all('/data-mkt-item="([^"]+)"/', $html, $m);

        return $m[1];
    }

    private function conMarketingEnElMenuLateral(): void
    {
        DB::table('module_sidebar_config')->updateOrInsert(
            ['module_key' => 'marketing'],
            ['show_in_sidebar' => 1, 'sidebar_location' => 'direct', 'sidebar_section' => 'modulos', 'sidebar_position' => 60,
             'sidebar_label' => 'Marketing', 'sidebar_url' => '/marketing', 'is_core' => 0, 'config_moved' => 0,
             'created_at' => now(), 'updated_at' => now()]
        );
        cache()->forget('sidebar_visible_items');
    }

    public function test_el_menu_lateral_del_admin_tiene_las_7_secciones_aprobadas_en_orden(): void
    {
        $this->conMarketingEnElMenuLateral();
        $admin = $this->usuario('admin');
        // Los permisos de las pantallas existen en megaisp_test pero no están asignados al rol: se dan al usuario de prueba.
        $admin->givePermissionTo(['view-video-templates', 'view-publishing-dashboard', 'marketing.leads.view', 'marketing.brand_kit.configure']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = $admin->fresh();

        $html = $this->como($admin)->get('/marketing/campanas-360')->assertOk()->getContent();

        $this->assertStringContainsString('data-mkt-submenu', $html);
        $this->assertSame(
            ['panel', 'campanas-360', 'reels-y-videos', 'publicacion', 'leads', 'pruebas-ab', 'configuracion'],
            $this->seccionesDelMenu($html)
        );
        foreach (['/marketing/campanas-360', '/marketing/video-templates', '/marketing/publishing', '/marketing/leads',
                  '/marketing/pilot-campaigns', '/marketing/brand-kit'] as $url) {
            $this->assertStringContainsString('href="' . url($url) . '" data-mkt-item=', $html, "falta {$url} en el menú");
        }
        // Lo retirado ya no aparece: «Campañas»/«Plantillas» viejas, «Publicar» repetido, «Cola» suelta, «Campaña IA».
        foreach (['t-mkt-camp', 't-mkt-tpl', 't-mkt-pubcam', 't-mkt-queue', 't-mkt-ia', '?tab=templates'] as $viejo) {
            $this->assertStringNotContainsString($viejo, $html, "{$viejo} sigue en el menú");
        }
    }

    public function test_quien_no_pasa_la_regla_de_acceso_no_ve_marketing_en_el_menu(): void
    {
        $this->conMarketingEnElMenuLateral();

        $casos = [
            'fila marketing' => $this->usuario('marketing'),
            'fila inactiva'  => $this->usuario('admin', false),
            'sin fila'       => $this->usuario(null),
        ];

        foreach ($casos as $caso => $u) {
            // La página de «sin acceso» usa el layout con el menú lateral.
            $html = $this->actingAs($u)->get('/marketing/campanas-360')->assertForbidden()->getContent();
            $this->assertStringNotContainsString('data-mkt-submenu', $html, "{$caso} ve el submenú de Marketing");
            $this->assertStringNotContainsString('t-marketing', $html, "{$caso} ve «Marketing» en el menú");
            $this->assertSame([], $this->seccionesDelMenu($html), $caso);
        }
    }

    public function test_una_seccion_del_menu_lateral_se_oculta_sin_su_permiso(): void
    {
        $this->conMarketingEnElMenuLateral();
        // Rol propio con todo lo del menú menos «marketing.leads.view» (el permiso de Leads, §2.11 ajustado).
        $rol = \Spatie\Permission\Models\Role::create(['name' => 'mkt_f2_front_sin_leads_' . Str::lower(Str::random(6)), 'guard_name' => 'web']);
        $rol->givePermissionTo(\Spatie\Permission\Models\Permission::whereIn('name', [
            'view-video-templates', 'view-publishing-dashboard', 'marketing.brand_kit.configure',
        ])->get());
        $u = $this->usuario('admin');
        $u->removeRole('super-administrator'); // usuario creado en esta prueba: se le deja solo el rol de prueba
        $u->assignRole($rol);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $html = $this->como($u->fresh())->get('/marketing/campanas-360')->assertOk()->getContent();

        $this->assertSame(
            ['panel', 'campanas-360', 'reels-y-videos', 'publicacion', 'pruebas-ab', 'configuracion'],
            $this->seccionesDelMenu($html)
        );
    }

    public function test_el_id_de_la_url_debe_ser_numerico(): void
    {
        $admin = $this->usuario('admin');

        $status = $this->como($admin)->get('/marketing/campanas-360/abc')->getStatusCode();
        $this->assertSame(404, $status, '/marketing/campanas-360/abc no debió montar la ficha');
    }
}
