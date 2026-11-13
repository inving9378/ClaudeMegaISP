<?php

namespace Tests\Feature\Marketing\Fase0\Backend;

use Tests\Feature\Marketing\Fase0\Backend\Concerns\EscenarioBackend;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 0 · backend — rutas 1 y 2: GET /api/marketing/contexto y POST /api/marketing/contexto/empresa-activa.
 */
class ContextoEmpresaBackendTest extends MarketingTestCase
{
    use EscenarioBackend;

    private const CLAVE = 'marketing.empresa_activa_id';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPermisos();
    }

    public function test_forma_exacta_del_contexto_para_un_admin_de_meganet(): void
    {
        $u = $this->crearUsuario(['super-administrator'], ['name' => 'Henry', 'father_last_name' => 'Pérez', 'mother_last_name' => null]);
        $u->givePermissionTo('marketing.empresas.gestionar');
        $b = $this->crearEmpresa(['nombre' => 'Meganet Empresarial', 'slug' => 'meganet-empresarial-' . uniqid(), 'color_primario' => '#1F3A93']);
        $this->asignar($u, 1, 'admin');
        $this->asignar($u, $b->id, 'ventas');

        $r = $this->actingAs($u)->getJson('/api/marketing/contexto')->assertOk();

        $r->assertJson([
            'ok'      => true,
            'usuario' => ['id' => $u->id, 'nombre' => 'Henry Pérez'],
            'empresa_activa' => [
                'id' => 1, 'nombre' => 'Meganet', 'slug' => 'meganet',
                'color_primario' => '#0066CC', 'color_secundario' => '#FF6600', 'rol' => 'admin',
            ],
            'capacidades' => ['gestionar_empresas' => true, 'editar_config_compartida' => true],
        ]);
        $this->assertSame(
            [
                ['id' => 1, 'nombre' => 'Meganet', 'slug' => 'meganet', 'color_primario' => '#0066CC', 'rol' => 'admin'],
                ['id' => $b->id, 'nombre' => 'Meganet Empresarial', 'slug' => $b->slug, 'color_primario' => '#1F3A93', 'rol' => 'ventas'],
            ],
            $r->json('empresas')
        );
        $this->assertSame(['ok', 'usuario', 'empresa_activa', 'empresas', 'capacidades'], array_keys($r->json()));
    }

    public function test_capacidades_segun_rol_permiso_y_empresa(): void
    {
        $sinPermiso = $this->crearUsuario(['Vendedor']);
        $this->asignar($sinPermiso, 1, 'admin');
        $this->actingAs($sinPermiso)->getJson('/api/marketing/contexto')
            ->assertJsonPath('capacidades.gestionar_empresas', false, 'admin de empresa sin el permiso Spatie')
            ->assertJsonPath('capacidades.editar_config_compartida', true);

        $ventas = $this->crearUsuario(['super-administrator']);
        $this->asignar($ventas, 1, 'ventas');
        $this->actingAs($ventas)->getJson('/api/marketing/contexto')
            ->assertJsonPath('capacidades.gestionar_empresas', false)
            ->assertJsonPath('capacidades.editar_config_compartida', false);

        $adminB = $this->crearUsuario(['super-administrator']);
        $b = $this->crearEmpresa();
        $this->asignar($adminB, $b->id, 'admin');
        $this->actingAs($adminB)->getJson('/api/marketing/contexto')
            ->assertJsonPath('capacidades.gestionar_empresas', true)
            ->assertJsonPath('capacidades.editar_config_compartida', false);
    }

    public function test_la_lista_de_empresas_excluye_inactivas_y_asignaciones_dadas_de_baja(): void
    {
        $u = $this->crearUsuario();
        $inactiva = $this->crearEmpresa(['activo' => false]);
        $baja = $this->crearEmpresa();
        $this->asignar($u, 1, 'marketing');
        $this->asignar($u, $inactiva->id, 'admin');
        $this->asignar($u, $baja->id, 'admin', false);

        $r = $this->actingAs($u)->getJson('/api/marketing/contexto')->assertOk();
        $this->assertSame([1], array_column($r->json('empresas'), 'id'));
    }

    public function test_cambiar_de_empresa_guarda_en_sesion_y_responde_la_nueva(): void
    {
        $u = $this->crearUsuario();
        $b = $this->crearEmpresa();
        $this->asignar($u, 1, 'admin');
        $this->asignar($u, $b->id, 'ventas');

        $this->actingAs($u)->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => $b->id])
            ->assertOk()
            ->assertJsonPath('empresa_activa.id', $b->id)
            ->assertJsonPath('empresa_activa.rol', 'ventas')
            ->assertJsonPath('capacidades.editar_config_compartida', false)
            ->assertSessionHas(self::CLAVE, $b->id);

        // La siguiente petición ya trabaja con B.
        $this->actingAs($u)->getJson('/api/marketing/contexto')->assertJsonPath('empresa_activa.id', $b->id);

        // Y se puede regresar (company_id como texto numérico también vale).
        $this->actingAs($u)->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => '1'])
            ->assertOk()->assertJsonPath('empresa_activa.id', 1)->assertSessionHas(self::CLAVE, 1);
    }

    public function test_cambiar_a_empresa_ajena_inactiva_o_inexistente_es_404(): void
    {
        $u = $this->crearUsuario();
        $ajena = $this->crearEmpresa();
        $inactiva = $this->crearEmpresa(['activo' => false]);
        $baja = $this->crearEmpresa();
        $this->asignar($u, 1, 'admin');
        $this->asignar($u, $inactiva->id, 'admin');
        $this->asignar($u, $baja->id, 'admin', false);

        foreach ([$ajena->id, $inactiva->id, $baja->id, 999999] as $id) {
            $this->actingAs($u)->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => $id])
                ->assertStatus(404)->assertJsonPath('ok', false);
        }

        $this->actingAs($u)->getJson('/api/marketing/contexto')->assertJsonPath('empresa_activa.id', 1);
    }

    public function test_cambiar_valida_company_id(): void
    {
        $u = $this->crearUsuario();
        $this->asignar($u, 1, 'admin');

        foreach ([[], ['company_id' => null], ['company_id' => 'abc'], ['company_id' => 1.5], ['company_id' => [1]], ['company_id' => 0]] as $cuerpo) {
            $this->actingAs($u)->postJson('/api/marketing/contexto/empresa-activa', $cuerpo)
                ->assertStatus(422)->assertJsonValidationErrors('company_id');
        }

        // Inyección: la frena el WAF global (403) o la validación (422); nunca cambia de empresa.
        $r = $this->actingAs($u)->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => '1 OR 1=1']);
        $this->assertContains($r->status(), [403, 422]);
    }

    public function test_cambiar_sin_empresa_responde_sin_empresa(): void
    {
        $u = $this->crearUsuario();

        $this->actingAs($u)->postJson('/api/marketing/contexto/empresa-activa', ['company_id' => 1])
            ->assertStatus(403)->assertJsonPath('error', 'sin_empresa');
    }
}
