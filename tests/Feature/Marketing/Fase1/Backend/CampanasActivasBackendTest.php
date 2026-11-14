<?php

namespace Tests\Feature\Marketing\Fase1\Backend;

use App\Modules\Addons\Marketing\Services\Dashboard\DashboardMarketingService;
use Tests\Feature\Marketing\Fase1\Backend\Concerns\EscenarioBackendFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/** GET /api/marketing/dashboard/campanas-activas (§2.4.2). */
class CampanasActivasBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase1;

    private function servicio(): DashboardMarketingService
    {
        return app(DashboardMarketingService::class);
    }

    public function test_forma_orden_limite_y_numeros(): void
    {
        $e = (int) $this->crearEmpresa()->id;
        $otra = (int) $this->crearEmpresa()->id;

        $vieja = $this->crearCampana($e, ['name' => 'Vieja', 'start_date' => '2026-09-01', 'budget' => '100.00', 'spent' => '10.00']);
        $nueva = $this->crearCampana($e, [
            'name' => 'Fibra noviembre', 'type' => 'meta_ads', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
            'budget' => '5000.00', 'spent' => '1200.50', 'currency' => 'MXN',
        ]);
        $media = $this->crearCampana($e, ['name' => 'Dólares', 'type' => 'google_ads', 'start_date' => '2026-10-01', 'budget' => '0.00', 'spent' => '3.00', 'currency' => 'usd']);
        $this->crearCampana($e, ['status' => 'paused', 'start_date' => '2026-12-01']);
        $this->crearCampana($e, ['status' => 'draft', 'start_date' => '2026-12-01']);
        $this->crearCampana($otra, ['start_date' => '2026-12-31']);
        $this->crearCampana($e, ['start_date' => '2026-12-31'])->delete();

        // Leads atribuidos a «nueva»: 2 distintos (uno con dos toques) + uno borrado que no cuenta.
        $l1 = $this->crearLead($e, 'new', '2026-11-02 00:00:00');
        $l2 = $this->crearLead($e, 'won', '2026-11-03 00:00:00');
        $l3 = $this->crearLead($e, 'new', '2026-11-04 00:00:00');
        $this->atribuir($l1->id, $nueva->id);
        $this->atribuir($l1->id, $nueva->id);
        $this->atribuir($l2->id, $nueva->id);
        $this->atribuir($l3->id, $nueva->id);
        $l3->delete();

        $r = $this->servicio()->campanasActivas($e, 2);

        $this->assertTrue($r['ok']);
        $this->assertSame(3, $r['total']);
        $this->assertSame([$nueva->id, $media->id], array_column($r['data'], 'id'));
        $this->assertSame([
            'id' => $nueva->id, 'nombre' => 'Fibra noviembre', 'tipo' => 'meta_ads', 'tipo_etiqueta' => 'Meta Ads',
            'estado' => 'active', 'moneda' => 'MXN', 'moneda_distinta' => false,
            'presupuesto' => '5000.00', 'gastado' => '1200.50', 'avance_presupuesto' => 24.0,
            'inicio' => '2026-11-01', 'fin' => '2026-11-30', 'leads' => 2,
        ], $r['data'][0]);

        $usd = $r['data'][1];
        $this->assertSame('USD', $usd['moneda']);
        $this->assertTrue($usd['moneda_distinta']);
        $this->assertSame('3.00', $usd['gastado']);
        $this->assertNull($usd['avance_presupuesto']);
        $this->assertNull($usd['fin']);
        $this->assertSame('Google Ads', $usd['tipo_etiqueta']);
        $this->assertSame(0, $usd['leads']);

        $this->assertSame([$nueva->id, $media->id, $vieja->id], array_column($this->servicio()->campanasActivas($e, 20)['data'], 'id'));
    }

    public function test_sin_campanas_activas(): void
    {
        $this->assertSame(['ok' => true, 'total' => 0, 'data' => []], $this->servicio()->campanasActivas((int) $this->crearEmpresa()->id, 5));
    }

    public function test_endpoint_default_cinco_y_solo_la_empresa_principal(): void
    {
        $admin = $this->adminMeganet();
        $propia = $this->crearCampana(1, ['start_date' => '2099-01-01']);
        $ajena = $this->crearCampana((int) $this->crearEmpresa()->id, ['start_date' => '2099-01-02']);

        $r = $this->actingAs($admin)->getJson('/api/marketing/dashboard/campanas-activas')->assertOk()->json();

        $this->assertTrue($r['ok']);
        $this->assertLessThanOrEqual(5, count($r['data']));
        $this->assertSame($propia->id, $r['data'][0]['id']);
        $this->assertNotContains($ajena->id, array_column($r['data'], 'id'));

        $this->actingAs($admin)->getJson('/api/marketing/dashboard/campanas-activas?limite=1')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/marketing/dashboard/campanas-activas?limite=20')->assertOk();
    }

    public function test_limite_invalido_responde_422(): void
    {
        $admin = $this->adminMeganet();

        foreach (['0', '21', 'abc', '', '-1', '2.5'] as $limite) {
            $this->actingAs($admin)->getJson('/api/marketing/dashboard/campanas-activas?limite=' . urlencode($limite))
                ->assertStatus(422)->assertJsonValidationErrors('limite');
        }
        // Con texto tipo SQL el WAF global (SqlInjectionProtection) puede cortar antes con 403: nunca 200.
        $status = $this->actingAs($admin)->getJson('/api/marketing/dashboard/campanas-activas?limite=' . urlencode('5 OR 1=1'))->status();
        $this->assertContains($status, [403, 422]);

        $this->actingAs($admin)->getJson('/api/marketing/dashboard/campanas-activas?limite[]=3')
            ->assertStatus(422)->assertJsonValidationErrors('limite');
    }

    public function test_acceso_negado_fuera_del_equipo_admin(): void
    {
        $marketing = $this->crearUsuario();
        $this->asignar($marketing, 1, 'marketing');

        $this->actingAs($marketing)->getJson('/api/marketing/dashboard/campanas-activas')->assertForbidden()->assertJsonPath('error', 'sin_acceso');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/marketing/dashboard/campanas-activas')->assertUnauthorized();
    }
}
