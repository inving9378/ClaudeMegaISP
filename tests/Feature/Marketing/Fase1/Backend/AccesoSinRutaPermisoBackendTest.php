<?php

namespace Tests\Feature\Marketing\Fase1\Backend;

use App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva;
use Illuminate\Routing\Route;
use Tests\Feature\Marketing\Fase1\Backend\Concerns\EscenarioBackendFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * F1-1 con las condiciones de Henry (§2.6.1) y la regla B del cambio de alcance (2026-11-13):
 * - ninguna ruta del módulo lleva `check_route_permission`; toda ruta no pública lleva `auth` y
 *   `marketing.empresa` (lista cerrada de públicas por nombre);
 * - sin sesión nunca 200; usuario inactivo → `usuario_inactivo`;
 * - solo entra quien es admin activo de la empresa principal (también se niega al super-administrador sin
 *   esa fila y a las filas `marketing`/`ventas`).
 */
class AccesoSinRutaPermisoBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase1;

    /** Públicas por diseño (formulario por slug, webhooks, tracking del piloto). Lista CERRADA. */
    private const PUBLICAS = [
        'marketing.webhook.meta-ads',
        'marketing.webhook.evolution',
        'marketing.public.form.show',
        'marketing.public.form.submit',
        'marketing.public.embed.js',
        'marketing.pilot.track.open',
        'marketing.pilot.track.click',
    ];

    /** @return array<int, Route> */
    private function rutasDelModulo(): array
    {
        $rutas = [];
        foreach (app('router')->getRoutes()->getRoutes() as $r) {
            $uri = $r->uri();
            if (preg_match('#^(api/marketing|marketing|webhooks/marketing|public/marketing)(/|$)#', $uri)) {
                $rutas[] = $r;
            }
        }

        return $rutas;
    }

    private function url(Route $r): string
    {
        return '/' . preg_replace('/\{[^}]+\}/', '1', $r->uri());
    }

    public function test_hay_134_rutas_y_ninguna_lleva_check_route_permission(): void
    {
        $rutas = $this->rutasDelModulo();
        $this->assertCount(152, $rutas); // 134 + 18 de Fase 2 (Campañas 360)

        foreach ($rutas as $r) {
            $this->assertNotContains('check_route_permission', $r->gatherMiddleware(), $r->uri());
        }
    }

    public function test_toda_ruta_no_publica_lleva_auth_y_marketing_empresa(): void
    {
        $publicasVistas = [];
        foreach ($this->rutasDelModulo() as $r) {
            $mw = $r->gatherMiddleware();
            if (in_array($r->getName(), self::PUBLICAS, true)) {
                $publicasVistas[] = $r->getName();
                $this->assertNotContains('auth', $mw, $r->getName());
                continue;
            }
            $this->assertContains('auth', $mw, $r->uri() . ' debe exigir sesión');
            $this->assertContains('marketing.empresa', $mw, $r->uri() . ' debe pasar por marketing.empresa');
        }

        sort($publicasVistas);
        $esperadas = self::PUBLICAS;
        sort($esperadas);
        $this->assertSame($esperadas, $publicasVistas);
    }

    public function test_sin_sesion_ninguna_ruta_no_publica_responde_200(): void
    {
        foreach ($this->rutasDelModulo() as $r) {
            if (in_array($r->getName(), self::PUBLICAS, true)) {
                continue;
            }
            foreach (array_diff($r->methods(), ['HEAD']) as $metodo) {
                $json = $this->json($metodo, $this->url($r));
                $this->assertContains($json->status(), [401, 419], "{$metodo} {$r->uri()} (API) sin sesión");

                if ($metodo === 'GET') {
                    $this->get($this->url($r))->assertRedirect(route('login'));
                }
            }
        }
    }

    public function test_admin_de_meganet_sin_roles_de_sistema_entra_con_su_permiso(): void
    {
        $this->usarLayoutMinimo();
        $admin = $this->adminMeganet(['marketing.leads.view', 'marketing.forms.view']);

        $this->assertFalse($admin->roles()->exists());
        $this->actingAs($admin)->getJson('/api/marketing/leads')->assertOk();
        $this->actingAs($admin)->getJson('/api/marketing/lead-forms')->assertOk();
        $this->actingAs($admin)->get('/marketing/leads')->assertOk();
        $this->actingAs($admin)->get('/marketing/lead-forms')->assertOk();
        $this->actingAs($admin)->getJson('/api/marketing/publishing/channels')->assertOk();
    }

    public function test_sin_ninguna_asignacion_responde_sin_empresa(): void
    {
        $this->usarLayoutMinimo();
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)->getJson('/api/marketing/conversations')->assertForbidden()
            ->assertExactJson(['ok' => false, 'error' => 'sin_empresa', 'mensaje' => ResolverEmpresaActiva::MENSAJES['sin_empresa']]);
        $this->actingAs($usuario)->get('/marketing/leads')->assertForbidden();
    }

    public function test_super_administrador_y_desarrollador_sin_fila_entran_por_su_rol(): void
    {
        // Acceso por ROL (Irving, 2026-11-14): antes la regla B los dejaba fuera sin fila admin; ahora entran por su rol.
        config(['marketing.roles_con_acceso' => self::ROLES_CON_ACCESO]);
        foreach (['super-administrator', 'DESARROLLADOR'] as $rol) {
            $usuario = $this->crearUsuario([$rol]);
            $res = $this->actingAs($usuario)->getJson('/api/marketing/navegacion');
            $this->assertNotContains($res->status(), [401, 403], "{$rol} sin fila debe entrar por su rol ({$res->status()})");
            $this->assertNotContains($res->json('error'), ['sin_empresa', 'sin_acceso']);
        }
    }

    public function test_filas_que_no_son_admin_de_meganet_responden_sin_acceso(): void
    {
        $this->usarLayoutMinimo();
        $otra = (int) $this->crearEmpresa()->id;

        $casos = [
            'ventas en Meganet'     => fn ($u) => $this->asignar($u, 1, 'ventas'),
            'marketing en Meganet'  => fn ($u) => $this->asignar($u, 1, 'marketing'),
            'admin de otra empresa' => fn ($u) => $this->asignar($u, $otra, 'admin'),
            'admin inactivo en Meganet + marketing activo' => function ($u) use ($otra) {
                $this->asignar($u, 1, 'admin', false);
                $this->asignar($u, $otra, 'marketing');
            },
        ];

        foreach ($casos as $caso => $preparar) {
            $usuario = $this->crearUsuario(['super-administrator']);
            $preparar($usuario);

            $this->actingAs($usuario)->getJson('/api/marketing/leads')->assertForbidden()
                ->assertExactJson(['ok' => false, 'error' => 'sin_acceso', 'mensaje' => ResolverEmpresaActiva::MENSAJES['sin_acceso']]);
            $this->assertSame(403, $this->actingAs($usuario)->get('/marketing/leads')->status(), $caso);
        }
    }

    public function test_usuario_inactivo_se_rechaza_y_se_cierra_su_sesion(): void
    {
        $admin = $this->adminMeganet(['marketing.leads.view']);
        $admin->forceFill(['estado' => 'inactivo'])->save();

        $this->actingAs($admin)->getJson('/api/marketing/leads')->assertUnauthorized()
            ->assertExactJson(['ok' => false, 'error' => 'usuario_inactivo', 'mensaje' => 'Tu usuario está inactivo.']);
        $this->assertGuest();

        $this->actingAs($admin)->get('/marketing/leads')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_la_empresa_activa_siempre_es_la_principal(): void
    {
        $admin = $this->adminMeganet();
        $otra = (int) $this->crearEmpresa()->id;
        $this->asignar($admin, $otra, 'admin');

        $this->actingAs($admin)->withSession([ResolverEmpresaActiva::CLAVE_SESION => $otra])
            ->getJson('/api/marketing/navegacion')->assertOk();
        $this->actingAs($admin)->withSession([ResolverEmpresaActiva::CLAVE_SESION => $otra])
            ->getJson('/api/marketing/dashboard/resumen')->assertOk()->assertJsonPath('empresa_id', 1);
    }

    /** QA F-4: un id no numérico en la URL daba 500 (TypeError con `int $id`); ahora ninguna ruta coincide → 404. */
    public function test_id_no_numerico_en_la_url_responde_404_y_nunca_500(): void
    {
        $admin = $this->adminMeganet(self::PERMISOS_FASE1);
        $probadas = 0;

        foreach ($this->rutasDelModulo() as $r) {
            if (! preg_match('/\{(id|campaignId|sendId|userId)\}/', $r->uri())) {
                continue;
            }
            foreach (['abc', '1abc', '-1', '1.5', '99999999999999999999'] as $malo) {
                $url = '/' . preg_replace(
                    ['/\{(id|campaignId|sendId|userId)\}/', '/\{niche\}/', '/\{[^}]+\}/'],
                    [$malo, 'gamer', '1'],
                    $r->uri()
                );
                foreach (array_diff($r->methods(), ['HEAD']) as $metodo) {
                    $status = $this->actingAs($admin)->json($metodo, $url)->status();
                    $this->assertNotSame(500, $status, "{$metodo} {$url}");
                    $this->assertSame(404, $status, "{$metodo} {$url}");
                }
            }
            $probadas++;
        }

        $this->assertGreaterThanOrEqual(50, $probadas);
    }

    public function test_el_mensaje_sin_empresa_ya_no_manda_a_empresas_y_equipo(): void
    {
        $this->assertSame('No tienes acceso a Marketing. Pide acceso a un administrador de Marketing.', ResolverEmpresaActiva::MENSAJES['sin_empresa']);

        $this->usarLayoutMinimo();
        $this->actingAs($this->crearUsuario())->get('/marketing/leads')->assertForbidden()
            ->assertSee('No tienes acceso a Marketing. Pide acceso a un administrador de Marketing.')
            ->assertDontSee('Empresas y equipo');
    }
}
