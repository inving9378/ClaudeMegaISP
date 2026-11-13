<?php

namespace Tests\Feature\Marketing\Fase0\Backend;

use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase0\Backend\Concerns\EscenarioBackend;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 0 · backend — middleware marketing.empresa / marketing.rol / marketing.principal.
 * Se usan rutas cuyo controlador no exige más permisos (lead-sources, contexto) para aislar la capa.
 */
class CapaEmpresaMiddlewareTest extends MarketingTestCase
{
    use EscenarioBackend;

    private const CLAVE = 'marketing.empresa_activa_id';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Bus::fake();
        $this->prepararPermisos();
    }

    // ── marketing.empresa ─────────────────────────────────────────────────────

    public function test_sin_sesion_responde_401(): void
    {
        $this->getJson('/api/marketing/contexto')->assertStatus(401);
    }

    public function test_sin_empresa_responde_403_json_con_mensaje(): void
    {
        $u = $this->crearUsuario();

        $this->actingAs($u)->getJson('/api/marketing/contexto')
            ->assertStatus(403)
            ->assertExactJson([
                'ok'      => false,
                'error'   => 'sin_empresa',
                'mensaje' => 'Todavía no perteneces a ninguna empresa de Marketing. Pide a un administrador de Marketing que te agregue en «Empresas y equipo».',
            ]);
    }

    public function test_sin_empresa_en_navegacion_muestra_la_pantalla_explicativa(): void
    {
        $this->usarLayoutMinimo();
        $u = $this->crearUsuario();

        $respuesta = $this->actingAs($u)->get('/marketing/empresas');

        $respuesta->assertStatus(403);
        $respuesta->assertSee('Aún no tienes empresa en Marketing');
        $respuesta->assertSee('Pide a un administrador de Marketing que te agregue en «Empresas y equipo».', false);
    }

    public function test_sin_sesion_previa_elige_la_empresa_de_menor_id_y_la_guarda(): void
    {
        $u = $this->crearUsuario();
        $b = $this->crearEmpresa();
        $this->asignar($u, $b->id, 'ventas');
        $this->asignar($u, 1, 'admin');

        $this->actingAs($u)->getJson('/api/marketing/contexto')
            ->assertOk()
            ->assertJsonPath('empresa_activa.id', 1)
            ->assertJsonPath('empresa_activa.rol', 'admin')
            ->assertSessionHas(self::CLAVE, 1);
    }

    public function test_respeta_la_empresa_guardada_en_sesion(): void
    {
        $u = $this->crearUsuario();
        $b = $this->crearEmpresa();
        $this->asignar($u, 1, 'admin');
        $this->asignar($u, $b->id, 'ventas');

        $this->actingAs($u)->withSession([self::CLAVE => $b->id])->getJson('/api/marketing/contexto')
            ->assertOk()
            ->assertJsonPath('empresa_activa.id', $b->id)
            ->assertJsonPath('empresa_activa.rol', 'ventas');
    }

    public function test_sesion_con_empresa_ajena_o_basura_cae_a_la_primera_propia(): void
    {
        $u = $this->crearUsuario();
        $ajena = $this->crearEmpresa();
        $this->asignar($u, 1, 'marketing');

        foreach ([$ajena->id, 999999, 'basura', ['x' => 1], null] as $valor) {
            $this->actingAs($u)->withSession([self::CLAVE => $valor])->getJson('/api/marketing/contexto')
                ->assertOk()
                ->assertJsonPath('empresa_activa.id', 1)
                ->assertSessionHas(self::CLAVE, 1);
        }
    }

    public function test_empresa_inactiva_o_asignacion_inactiva_no_cuentan(): void
    {
        $u = $this->crearUsuario();
        $inactiva = $this->crearEmpresa(['activo' => false]);
        $this->asignar($u, $inactiva->id, 'admin');
        $this->asignar($u, 1, 'admin', false);

        $this->actingAs($u)->withSession([self::CLAVE => $inactiva->id])->getJson('/api/marketing/contexto')
            ->assertStatus(403)->assertJsonPath('error', 'sin_empresa');
    }

    public function test_el_contexto_se_limpia_al_terminar_la_peticion(): void
    {
        $u = $this->crearUsuario();
        $this->asignar($u, 1, 'admin');

        $this->actingAs($u)->getJson('/api/marketing/contexto')->assertOk();

        $this->assertFalse(app(EmpresaActiva::class)->hay(), 'no se arrastra a lo que corre después de responder');
    }

    public function test_rutas_publicas_no_fijan_contexto_aunque_haya_sesion(): void
    {
        $u = $this->crearUsuario();
        $this->asignar($u, 1, 'admin');

        $this->actingAs($u)->get('/public/marketing/embed.js')->assertOk();

        $this->assertFalse(app(EmpresaActiva::class)->hay());
    }

    // ── marketing.rol ─────────────────────────────────────────────────────────

    public function test_ventas_entra_a_las_rutas_del_grupo_v(): void
    {
        $u = $this->crearUsuario();
        $this->asignar($u, 1, 'ventas');

        $this->actingAs($u)->getJson('/api/marketing/lead-sources')->assertOk();
    }

    public function test_ventas_recibe_403_en_rutas_del_grupo_m(): void
    {
        $u = $this->crearUsuario();
        $this->asignar($u, 1, 'ventas');

        $esperado = ['ok' => false, 'error' => 'sin_permiso_rol', 'mensaje' => 'Tu rol en esta empresa no permite esta acción.'];

        $this->actingAs($u)->getJson('/api/marketing/lead-forms')->assertStatus(403)->assertExactJson($esperado);
        $this->actingAs($u)->deleteJson('/api/marketing/leads/1')->assertStatus(403)->assertExactJson($esperado);
        $this->actingAs($u)->postJson('/api/marketing/leads/1/score')->assertStatus(403)->assertExactJson($esperado);
        $this->actingAs($u)->getJson('/api/marketing/publishing/dashboard/stats')->assertStatus(403)->assertJsonPath('error', 'sin_permiso_rol');
    }

    public function test_marketing_no_entra_a_empresas_y_equipo(): void
    {
        $u = $this->crearUsuario();
        $this->asignar($u, 1, 'marketing');

        $this->actingAs($u)->getJson('/api/marketing/empresas')
            ->assertStatus(403)->assertJsonPath('error', 'sin_permiso_rol');
    }

    public function test_sin_permiso_rol_en_navegacion_usa_la_pagina_403(): void
    {
        $u = $this->crearUsuario();
        $this->asignar($u, 1, 'ventas');

        $this->actingAs($u)->get('/marketing/empresas')->assertStatus(403)->assertSee('403');
    }

    // ── marketing.principal ───────────────────────────────────────────────────

    public function test_config_compartida_con_otra_empresa_activa_responde_403(): void
    {
        $u = $this->crearUsuario();
        $b = $this->crearEmpresa();
        $this->asignar($u, $b->id, 'admin');

        $this->actingAs($u)->withSession([self::CLAVE => $b->id])
            ->putJson('/api/marketing/niches/1', ['name' => 'x'])
            ->assertStatus(403)
            ->assertExactJson([
                'ok'      => false,
                'error'   => 'config_compartida',
                'mensaje' => 'Esta configuración es de Meganet y la comparten todas las empresas; solo se cambia con Meganet como empresa activa.',
            ]);
    }

    public function test_config_compartida_con_meganet_activa_pasa_la_capa(): void
    {
        $u = $this->crearUsuario();
        $this->asignar($u, 1, 'admin');

        // Pasa la capa por empresa; lo que responda después es del controlador (no 403 de la capa).
        $r = $this->actingAs($u)->putJson('/api/marketing/niches/999999', ['name' => 'x']);
        $this->assertNotContains($r->json('error'), ['sin_empresa', 'sin_permiso_rol', 'config_compartida']);
    }
}
