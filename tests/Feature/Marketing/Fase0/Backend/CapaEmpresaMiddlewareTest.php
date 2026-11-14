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
 *
 * Ajustes Fase 1 (QA, cambio de alcance B de Henry 2026-11-13: solo entra el admin activo de la empresa 1;
 * la empresa activa SIEMPRE es la 1; filas marketing/ventas → 403 `sin_acceso`). Cada prueba cambiada
 * lleva «[Fase 1 · B]» con su razón; ninguna baja la exigencia de negar.
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
                'mensaje' => 'No tienes acceso a Marketing. Pide acceso a un administrador de Marketing.', // [Fase 1] texto nuevo: «Empresas y equipo» está oculta,
            ]);
    }

    public function test_sin_empresa_en_navegacion_muestra_la_pantalla_explicativa(): void
    {
        $this->usarLayoutMinimo();
        $u = $this->crearUsuario();

        $respuesta = $this->actingAs($u)->get('/marketing/empresas');

        $respuesta->assertStatus(403);
        $respuesta->assertSee('No tienes acceso a Marketing');
        $respuesta->assertSee('No tienes acceso a Marketing. Pide acceso a un administrador de Marketing.', false); // [Fase 1] texto nuevo
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

    /** [Fase 1 · B] Antes: respetaba la empresa guardada en sesión (B, rol ventas). Ahora la activa es siempre la 1. */
    public function test_respeta_la_empresa_guardada_en_sesion(): void
    {
        $u = $this->crearUsuario();
        $b = $this->crearEmpresa();
        $this->asignar($u, 1, 'admin');
        $this->asignar($u, $b->id, 'ventas');

        $this->actingAs($u)->withSession([self::CLAVE => $b->id])->getJson('/api/marketing/contexto')
            ->assertOk()
            ->assertJsonPath('empresa_activa.id', 1)
            ->assertJsonPath('empresa_activa.rol', 'admin')
            ->assertSessionHas(self::CLAVE, 1);
    }

    public function test_sesion_con_empresa_ajena_o_basura_cae_a_la_primera_propia(): void
    {
        $u = $this->crearUsuario();
        $ajena = $this->crearEmpresa();
        $this->asignar($u, 1, 'admin'); // [Fase 1 · B] antes 'marketing': con la regla B un 'marketing' ya no entra

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

    /** [Fase 1 · B] Antes: ventas entraba al grupo V (200). Ahora ninguna fila que no sea admin entra. */
    public function test_ventas_entra_a_las_rutas_del_grupo_v(): void
    {
        $u = $this->crearUsuario();
        $this->asignar($u, 1, 'ventas');

        $this->actingAs($u)->getJson('/api/marketing/lead-sources')->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
    }

    public function test_ventas_recibe_403_en_rutas_del_grupo_m(): void
    {
        $u = $this->crearUsuario();
        $this->asignar($u, 1, 'ventas');

        // [Fase 1 · B] Antes `sin_permiso_rol` (lo daba marketing.rol); ahora corta antes marketing.empresa con `sin_acceso`.
        $esperado = ['ok' => false, 'error' => 'sin_acceso', 'mensaje' => 'Marketing está abierto a super-administrador, Administrador, Desarrollador y Mostrador. Si necesitas entrar, pídele a un administrador que te asigne uno de esos roles.'];

        $this->actingAs($u)->getJson('/api/marketing/lead-forms')->assertStatus(403)->assertExactJson($esperado);
        $this->actingAs($u)->deleteJson('/api/marketing/leads/1')->assertStatus(403)->assertExactJson($esperado);
        $this->actingAs($u)->postJson('/api/marketing/leads/1/score')->assertStatus(403)->assertExactJson($esperado);
        $this->actingAs($u)->getJson('/api/marketing/publishing/dashboard/stats')->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
    }

    public function test_marketing_no_entra_a_empresas_y_equipo(): void
    {
        $u = $this->crearUsuario();
        $this->asignar($u, 1, 'marketing');

        // [Fase 1 · B] Antes `sin_permiso_rol`; ahora `sin_acceso` (la fila marketing no entra a nada).
        $this->actingAs($u)->getJson('/api/marketing/empresas')
            ->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
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

        // [Fase 1 · B] Antes `config_compartida`: con la regla B un admin de otra empresa ni siquiera entra
        // (`sin_acceso`), y la empresa activa siempre es Meganet. Sigue siendo 403 y no toca el nicho.
        $this->actingAs($u)->withSession([self::CLAVE => $b->id])
            ->putJson('/api/marketing/niches/1', ['name' => 'x'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'sin_acceso');
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
