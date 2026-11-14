<?php

namespace Tests\Feature\Marketing\Fase1\Backend;

use App\Models\Marketing\Lead;
use App\Modules\Addons\Marketing\Services\Dashboard\DashboardMarketingService;
use Carbon\CarbonImmutable;
use Tests\Feature\Marketing\Fase1\Backend\Concerns\EscenarioBackendFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * GET /api/marketing/dashboard/resumen (§2.4.1). Los números exactos se prueban sobre el servicio con una
 * empresa recién creada (nadie más escribe en ella); el endpoint se prueba con la empresa principal (regla
 * B de Henry: solo entra su admin) en un mes viejo sin datos ajenos.
 */
class DashboardResumenBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase1;

    private function servicio(): DashboardMarketingService
    {
        return app(DashboardMarketingService::class);
    }

    /** Octubre 2026 con todos los estados y bordes; septiembre como mes anterior. */
    private function escenario(): int
    {
        $e = (int) $this->crearEmpresa()->id;
        $otra = (int) $this->crearEmpresa()->id;

        // Octubre: 11 leads que cuentan.
        $this->crearLead($e, 'new', '2026-10-01 00:00:00');           // borde inicial
        $this->crearLead($e, 'new', '2026-10-15 12:00:00');
        $this->crearLead($e, 'new', '2026-10-31 23:59:59');           // borde final
        $this->crearLead($e, 'contacted', '2026-10-02 09:00:00');
        $this->crearLead($e, 'contacted', '2026-10-03 09:00:00');
        $this->crearLead($e, 'qualified', '2026-10-04 09:00:00');
        $this->crearLead($e, 'negotiating', '2026-10-05 09:00:00');
        $this->crearLead($e, 'won', '2026-10-06 09:00:00');
        $this->crearLead($e, 'won', '2026-10-07 09:00:00');
        $this->crearLead($e, 'lost', '2026-10-08 09:00:00');
        $this->crearLead($e, 'archived', '2026-10-09 09:00:00');
        // Octubre que NO cuentan: otra empresa, borrado lógico, fuera del mes.
        $this->crearLead($otra, 'won', '2026-10-10 09:00:00');
        $this->crearLead($e, 'won', '2026-10-11 09:00:00')->delete();
        $this->crearLead($e, 'won', '2026-11-01 00:00:00');
        $this->crearLead($e, 'won', '2026-09-30 23:59:59');            // es de septiembre

        // Septiembre: 4 leads (el de arriba + 3), 1 ganado.
        $this->crearLead($e, 'new', '2026-09-01 00:00:00');
        $this->crearLead($e, 'new', '2026-09-10 00:00:00');
        $this->crearLead($e, 'contacted', '2026-09-20 00:00:00');

        // Campañas.
        $this->crearCampana($e, ['status' => 'active', 'currency' => 'MXN', 'spent' => '1000.01', 'start_date' => '2026-10-05', 'end_date' => null]);
        $this->crearCampana($e, ['status' => 'finished', 'currency' => 'MXN', 'spent' => '0.50', 'start_date' => '2026-09-01', 'end_date' => '2026-10-01']);
        $this->crearCampana($e, ['status' => 'paused', 'currency' => 'USD', 'spent' => '999.00', 'start_date' => '2026-10-01', 'end_date' => null]);
        $this->crearCampana($e, ['status' => 'draft', 'currency' => 'MXN', 'spent' => '5000.00', 'start_date' => '2026-10-01']);
        $this->crearCampana($e, ['status' => 'canceled', 'currency' => 'MXN', 'spent' => '7000.00', 'start_date' => '2026-10-01']);
        $this->crearCampana($e, ['status' => 'active', 'currency' => 'MXN', 'spent' => '300.00', 'start_date' => '2026-11-01']);
        $this->crearCampana($e, ['status' => 'finished', 'currency' => 'MXN', 'spent' => '200.00', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
        $this->crearCampana($e, ['status' => 'active', 'currency' => 'MXN', 'spent' => '50.00', 'start_date' => null]);
        $this->crearCampana($otra, ['status' => 'active', 'currency' => 'MXN', 'spent' => '8888.00', 'start_date' => '2026-10-01']);
        $this->crearCampana($e, ['status' => 'active', 'currency' => 'MXN', 'spent' => '9999.00', 'start_date' => '2026-10-01'])->delete();

        return $e;
    }

    public function test_kpis_con_numeros_exactos(): void
    {
        $e = $this->escenario();
        $r = $this->servicio()->resumen($e, '2026-10');

        $this->assertTrue($r['ok']);
        $this->assertSame($e, $r['empresa_id']);
        $this->assertSame([
            'mes' => '2026-10', 'desde' => '2026-10-01T00:00:00-06:00', 'hasta' => '2026-10-31T23:59:59-06:00', 'mes_anterior' => '2026-09',
        ], $r['periodo']);

        $k = $r['kpis'];
        $this->assertSame(['valor' => 11, 'anterior' => 4, 'variacion' => 175.0], $k['leads_mes']);
        // 2/11 = 18.18 → 18.2 ; 1/4 = 25.0 ; (18.2 − 25.0) / 25.0 = −27.2
        $this->assertSame(['valor' => 18.2, 'anterior' => 25.0, 'variacion' => -27.2, 'ganados' => 2], $k['conversion']);
        // Oct: 1000.01 + 0.50 ; Sep: 0.50 + 200.00 ; (1000.51 − 200.50) / 200.50 = 399.007 → 399.0
        $this->assertSame([
            'valor' => '1000.51', 'anterior' => '200.50', 'variacion' => 399.0, 'fuente' => 'campanas', 'campanas_otra_moneda' => 1,
        ], $k['gasto_mxn']);
        // 1000.51 / 11 = 90.955 → 90.96 ; 200.50 / 4 = 50.125 → 50.13 (half-up) ; (90.96 − 50.13) / 50.13 = 81.45 → 81.4
        $this->assertSame(['valor' => '90.96', 'anterior' => '50.13', 'variacion' => 81.4], $k['costo_por_lead_mxn']);

        $this->assertSame([
            ['clave' => 'new', 'etiqueta' => 'Nuevo', 'total' => 3],
            ['clave' => 'contacted', 'etiqueta' => 'Contactado', 'total' => 2],
            ['clave' => 'qualified', 'etiqueta' => 'Calificado', 'total' => 1],
            ['clave' => 'negotiating', 'etiqueta' => 'En negociación', 'total' => 1],
            ['clave' => 'won', 'etiqueta' => 'Ganado', 'total' => 2],
        ], $r['embudo']['etapas']);
        $this->assertSame(1, $r['embudo']['perdidos']);
        $this->assertSame(1, $r['embudo']['archivados']);
        $this->assertSame(11, $r['embudo']['total']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-0[56]:00$/', $r['generado_at']);
    }

    public function test_dinero_sale_como_cadena_con_dos_decimales(): void
    {
        $r = $this->servicio()->resumen($this->escenario(), '2026-10');

        $this->assertIsString($r['kpis']['gasto_mxn']['valor']);
        $this->assertIsString($r['kpis']['costo_por_lead_mxn']['valor']);
        $json = json_encode($r);
        $this->assertStringContainsString('"valor":"1000.51"', $json);
    }

    public function test_redondeo_half_up_del_costo_por_lead(): void
    {
        $e = (int) $this->crearEmpresa()->id;
        $this->crearLead($e, 'new', '2026-10-02 00:00:00');
        $this->crearLead($e, 'new', '2026-10-03 00:00:00');
        $this->crearCampana($e, ['spent' => '1000.01', 'start_date' => '2026-10-01']);

        // 1000.01 / 2 = 500.005 → 500.01
        $this->assertSame('500.01', $this->servicio()->resumen($e, '2026-10')['kpis']['costo_por_lead_mxn']['valor']);
    }

    public function test_sin_datos_todo_es_null_o_cero_y_siempre_las_cinco_etapas(): void
    {
        $e = (int) $this->crearEmpresa()->id;
        $r = $this->servicio()->resumen($e, '2026-10');

        $this->assertSame(['valor' => 0, 'anterior' => 0, 'variacion' => null], $r['kpis']['leads_mes']);
        $this->assertSame(['valor' => null, 'anterior' => null, 'variacion' => null, 'ganados' => 0], $r['kpis']['conversion']);
        $this->assertNull($r['kpis']['gasto_mxn']['valor']);
        $this->assertNull($r['kpis']['gasto_mxn']['anterior']);
        $this->assertSame(0, $r['kpis']['gasto_mxn']['campanas_otra_moneda']);
        $this->assertSame(['valor' => null, 'anterior' => null, 'variacion' => null], $r['kpis']['costo_por_lead_mxn']);
        $this->assertCount(5, $r['embudo']['etapas']);
        $this->assertSame([0, 0, 0, 0, 0], array_column($r['embudo']['etapas'], 'total'));
    }

    public function test_solo_campanas_en_otra_moneda_dan_gasto_null_y_se_cuentan(): void
    {
        $e = (int) $this->crearEmpresa()->id;
        $this->crearLead($e, 'new', '2026-10-02 00:00:00');
        $this->crearCampana($e, ['currency' => 'USD', 'spent' => '100.00', 'start_date' => '2026-10-01']);

        $g = $this->servicio()->resumen($e, '2026-10')['kpis']['gasto_mxn'];
        $this->assertNull($g['valor']);
        $this->assertSame(1, $g['campanas_otra_moneda']);
        $this->assertNull($this->servicio()->resumen($e, '2026-10')['kpis']['costo_por_lead_mxn']['valor']);
    }

    public function test_mes_anterior_en_cero_da_variacion_null(): void
    {
        $e = (int) $this->crearEmpresa()->id;
        $this->crearLead($e, 'won', '2026-10-02 00:00:00');

        $k = $this->servicio()->resumen($e, '2026-10')['kpis'];
        $this->assertSame(['valor' => 1, 'anterior' => 0, 'variacion' => null], $k['leads_mes']);
        $this->assertSame(100.0, $k['conversion']['valor']);
        $this->assertNull($k['conversion']['variacion']);
    }

    public function test_enero_compara_contra_diciembre_del_anio_anterior(): void
    {
        $e = (int) $this->crearEmpresa()->id;
        $this->crearLead($e, 'new', '2025-12-31 23:00:00');
        $this->crearLead($e, 'new', '2026-01-01 00:00:00');

        $r = $this->servicio()->resumen($e, '2026-01');
        $this->assertSame('2025-12', $r['periodo']['mes_anterior']);
        $this->assertSame(['valor' => 1, 'anterior' => 1, 'variacion' => 0.0], $r['kpis']['leads_mes']);
    }

    // ── Endpoint ─────────────────────────────────────────────────────────────

    public function test_endpoint_responde_la_empresa_principal_para_su_admin(): void
    {
        $admin = $this->adminMeganet();
        $this->crearLead(1, 'won', '2020-02-10 10:00:00');
        $esperados = Lead::query()->where('company_id', 1)
            ->whereBetween('captured_at', ['2020-02-01 00:00:00', '2020-02-29 23:59:59'])->count();

        $r = $this->actingAs($admin)->getJson('/api/marketing/dashboard/resumen?mes=2020-02')
            ->assertOk()
            ->assertJsonStructure([
                'ok', 'empresa_id', 'periodo' => ['mes', 'desde', 'hasta', 'mes_anterior'],
                'kpis' => [
                    'leads_mes' => ['valor', 'anterior', 'variacion'],
                    'conversion' => ['valor', 'anterior', 'variacion', 'ganados'],
                    'gasto_mxn' => ['valor', 'anterior', 'variacion', 'fuente', 'campanas_otra_moneda'],
                    'costo_por_lead_mxn' => ['valor', 'anterior', 'variacion'],
                ],
                'embudo' => ['etapas', 'perdidos', 'archivados', 'total'],
                'generado_at',
            ])
            ->json();

        $this->assertSame(1, $r['empresa_id']);
        $this->assertSame($esperados, $r['kpis']['leads_mes']['valor']);
        $this->assertSame('2020-01', $r['periodo']['mes_anterior']);
    }

    public function test_sin_mes_usa_el_mes_actual_de_mexico(): void
    {
        $this->actingAs($this->adminMeganet())->getJson('/api/marketing/dashboard/resumen')
            ->assertOk()
            ->assertJsonPath('periodo.mes', CarbonImmutable::now('America/Mexico_City')->format('Y-m'));
    }

    public function test_mes_invalido_responde_422_formato_laravel(): void
    {
        $admin = $this->adminMeganet();
        $siguiente = CarbonImmutable::now('America/Mexico_City')->addMonthNoOverflow()->format('Y-m');

        foreach (['', '2026-13', '2026-00', '2019-12', $siguiente, '2026-1', '2026/10', '<script>alert(1)</script>', "2026-10' OR '1'='1", '9999-12'] as $mes) {
            $this->actingAs($admin)->getJson('/api/marketing/dashboard/resumen?mes=' . urlencode($mes))
                ->assertStatus(422)
                ->assertJsonValidationErrors('mes');
        }

        $this->actingAs($admin)->getJson('/api/marketing/dashboard/resumen?mes[]=2026-10')
            ->assertStatus(422)->assertJsonValidationErrors('mes');
    }

    public function test_limites_del_rango_de_mes_son_validos(): void
    {
        $admin = $this->adminMeganet();
        $this->actingAs($admin)->getJson('/api/marketing/dashboard/resumen?mes=2020-01')->assertOk();
        $this->actingAs($admin)->getJson('/api/marketing/dashboard/resumen?mes=' . CarbonImmutable::now('America/Mexico_City')->format('Y-m'))->assertOk();
    }

    public function test_quien_no_es_admin_de_meganet_no_lo_ve(): void
    {
        $ventas = $this->crearUsuario();
        $this->asignar($ventas, 1, 'ventas');
        $this->actingAs($ventas)->getJson('/api/marketing/dashboard/resumen')->assertForbidden()->assertJsonPath('error', 'sin_acceso');

        $super = $this->crearUsuario(['super-administrator']);
        $this->actingAs($super)->getJson('/api/marketing/dashboard/resumen')->assertForbidden()->assertJsonPath('error', 'sin_empresa');
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->getJson('/api/marketing/dashboard/resumen')->assertUnauthorized();
    }
}
