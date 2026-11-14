<?php

namespace Tests\Feature\Marketing\Fase1\Qa;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase1\Qa\Concerns\EscenarioFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 1 — `GET /api/marketing/dashboard/resumen` (contrato §2.4.1, criterios 1 y 2).
 *
 * Números exactos contra un conteo hecho a mano. Para no depender de lo que ya haya en megaisp_test, la
 * prueba arranca con los leads y campañas de la empresa 1 marcados como borrados (soft delete DENTRO de la
 * transacción, se deshace al terminar): el contrato dice «sin filas borradas».
 *
 * Mes de prueba M = hace 2 meses (siempre válido y con mes anterior), así no depende del día de hoy.
 */
class DashboardKpisTest extends MarketingTestCase
{
    use EscenarioFase1;

    private CarbonImmutable $m;   // primer día del mes M (America/Mexico_City)
    private CarbonImmutable $m1;  // primer día del mes anterior

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
        $this->montarEscenarioFase1();
        DB::table('marketing_leads')->where('company_id', 1)->whereNull('deleted_at')->update(['deleted_at' => now()]);
        DB::table('marketing_campaigns')->where('company_id', 1)->whereNull('deleted_at')->update(['deleted_at' => now()]);
        $this->m = $this->hoy()->subMonthsNoOverflow(2)->startOfMonth();
        $this->m1 = $this->m->subMonthNoOverflow();
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function resumen(?string $mes = null)
    {
        $url = '/api/marketing/dashboard/resumen' . ($mes !== null ? '?mes=' . urlencode($mes) : '');

        return $this->como($this->adminA, 1)->getJson($url);
    }

    private function leads(CarbonImmutable $mes, array $porEstado, int $companyId = 1): void
    {
        $dia = 10;
        foreach ($porEstado as $estado => $n) {
            for ($i = 0; $i < $n; $i++) {
                $this->lead($companyId, $estado, $mes->setDay(min($dia++, 28))->setTime(12, 0));
            }
        }
    }

    /** Escenario del conteo a mano (ver docs/marketing/fase-1-qa-reporte.md). */
    private function sembrarConteoManual(): void
    {
        // M: 10 leads (2 ganados) → conversión 20.0. M-1: 8 leads (1 ganado) → 12.5.
        $this->leads($this->m, ['new' => 2, 'contacted' => 2, 'qualified' => 1, 'negotiating' => 1, 'won' => 2, 'lost' => 1, 'archived' => 1]);
        $this->leads($this->m1, ['new' => 6, 'won' => 1, 'lost' => 1]);
        // Fuera de M y M-1: no cuentan.
        $this->lead(1, 'won', $this->m->subYear()->setDay(15));
        $this->lead(1, 'won', $this->m->addMonth()->setDay(15)->min($this->hoy()));
        // Otra empresa en M: nunca cuenta (scope).
        $this->leads($this->m, ['won' => 5], $this->empresaB->id);
        // Borrado en M: no cuenta.
        DB::table('marketing_leads')->where('id', $this->lead(1, 'won', $this->m->setDay(20))->id)->update(['deleted_at' => now()]);

        $d = fn (CarbonImmutable $c) => $c->toDateString();
        // Califican en M: C1 (active, empieza en M) + C2 (finished, M-1 → día 5 de M).
        $this->campana(1, ['spent' => '1234.56', 'budget' => '5000.00', 'status' => 'active', 'start_date' => $d($this->m), 'end_date' => null]);
        $this->campana(1, ['spent' => '1000.00', 'budget' => '1000.00', 'status' => 'finished', 'start_date' => $d($this->m1), 'end_date' => $d($this->m->setDay(5))]);
        // No suman: USD (se cuenta aparte), draft, canceled, empieza después de M, terminó antes de M-1, otra empresa, borrada.
        $this->campana(1, ['spent' => '999.99', 'currency' => 'USD', 'status' => 'active', 'start_date' => $d($this->m)]);
        $this->campana(1, ['spent' => '500.00', 'status' => 'draft', 'start_date' => $d($this->m)]);
        $this->campana(1, ['spent' => '700.00', 'status' => 'canceled', 'start_date' => $d($this->m)]);
        $this->campana(1, ['spent' => '800.00', 'status' => 'active', 'start_date' => $d($this->m->addMonthNoOverflow())]);
        $this->campana(1, ['spent' => '900.00', 'status' => 'finished', 'start_date' => $d($this->m1->subMonthNoOverflow()), 'end_date' => $d($this->m1->subDay())]);
        $this->campana($this->empresaB->id, ['spent' => '5555.55', 'status' => 'active', 'start_date' => $d($this->m)]);
        $borrada = $this->campana(1, ['spent' => '4444.44', 'status' => 'active', 'start_date' => $d($this->m)]);
        DB::table('marketing_campaigns')->where('id', $borrada->id)->update(['deleted_at' => now()]);
    }

    // ── conteo manual ────────────────────────────────────────────────────────

    public function test_kpis_coinciden_con_el_conteo_manual(): void
    {
        $this->sembrarConteoManual();
        $r = $this->resumen($this->m->format('Y-m'))->assertOk();

        $r->assertJsonPath('ok', true);
        $r->assertJsonPath('empresa_id', 1);
        $r->assertJsonPath('periodo.mes', $this->m->format('Y-m'));
        $r->assertJsonPath('periodo.mes_anterior', $this->m1->format('Y-m'));
        $r->assertJsonPath('periodo.desde', $this->m->startOfMonth()->toIso8601String());
        $r->assertJsonPath('periodo.hasta', $this->m->endOfMonth()->startOfSecond()->toIso8601String());

        $k = $r->json('kpis');
        $this->assertSame(10, $k['leads_mes']['valor']);
        $this->assertSame(8, $k['leads_mes']['anterior']);
        $this->assertEquals(25.0, $k['leads_mes']['variacion']);

        $this->assertEquals(20.0, $k['conversion']['valor']);
        $this->assertEquals(12.5, $k['conversion']['anterior']);
        $this->assertEquals(60.0, $k['conversion']['variacion']);
        $this->assertSame(2, $k['conversion']['ganados']);

        // Gasto: cadena con 2 decimales, sin float. 1234.56 + 1000.00; anterior = 1000.00 (C2 toca M-1).
        $this->assertSame('2234.56', $k['gasto_mxn']['valor']);
        $this->assertSame('1000.00', $k['gasto_mxn']['anterior']);
        $this->assertEquals(123.5, $k['gasto_mxn']['variacion'], '123.456 → 123.5 (half-up)');
        $this->assertSame('campanas', $k['gasto_mxn']['fuente']);
        $this->assertSame(1, $k['gasto_mxn']['campanas_otra_moneda']);

        // 2234.56 / 10 = 223.456 → 223.46 ; anterior 1000.00 / 8 = 125.00 ; var 78.8
        $this->assertSame('223.46', $k['costo_por_lead_mxn']['valor']);
        $this->assertSame('125.00', $k['costo_por_lead_mxn']['anterior']);
        $this->assertEquals(78.8, $k['costo_por_lead_mxn']['variacion']);

        $e = $r->json('embudo');
        $this->assertSame([
            ['clave' => 'new', 'etiqueta' => 'Nuevo', 'total' => 2],
            ['clave' => 'contacted', 'etiqueta' => 'Contactado', 'total' => 2],
            ['clave' => 'qualified', 'etiqueta' => 'Calificado', 'total' => 1],
            ['clave' => 'negotiating', 'etiqueta' => 'En negociación', 'total' => 1],
            ['clave' => 'won', 'etiqueta' => 'Ganado', 'total' => 2],
        ], $e['etapas']);
        $this->assertSame(1, $e['perdidos']);
        $this->assertSame(1, $e['archivados']);
        $this->assertSame(10, $e['total']);
    }

    public function test_dinero_viaja_como_cadena_nunca_como_numero(): void
    {
        $this->sembrarConteoManual();
        $raw = (string) $this->resumen($this->m->format('Y-m'))->getContent();
        $this->assertMatchesRegularExpression('/"gasto_mxn":\{"valor":"2234\.56"/', $raw);
        $this->assertMatchesRegularExpression('/"costo_por_lead_mxn":\{"valor":"223\.46"/', $raw);
    }

    // ── vacíos y bordes ──────────────────────────────────────────────────────

    public function test_mes_sin_datos_da_null_nunca_un_cero_inventado(): void
    {
        $r = $this->resumen($this->m->format('Y-m'))->assertOk();
        $k = $r->json('kpis');
        $this->assertSame(0, $k['leads_mes']['valor']);
        $this->assertNull($k['conversion']['valor'], '0 leads → conversión null');
        $this->assertNull($k['gasto_mxn']['valor'], 'sin campañas → gasto null («—»)');
        $this->assertNull($k['costo_por_lead_mxn']['valor']);
        $this->assertNull($k['leads_mes']['variacion'], 'anterior 0 → variación null');
        $this->assertSame(0, $k['gasto_mxn']['campanas_otra_moneda']);
        $this->assertCount(5, $r->json('embudo.etapas'), 'Siempre las 5 etapas aunque estén en 0');
        $this->assertSame([0, 0, 0, 0, 0], array_column($r->json('embudo.etapas'), 'total'));
    }

    public function test_solo_campana_usd_da_gasto_null_y_la_cuenta_aparte(): void
    {
        $this->campana(1, ['spent' => '50.00', 'currency' => 'USD', 'status' => 'active', 'start_date' => $this->m->toDateString()]);
        $this->leads($this->m, ['new' => 3]);
        $k = $this->resumen($this->m->format('Y-m'))->assertOk()->json('kpis');
        $this->assertNull($k['gasto_mxn']['valor'], 'USD no se convierte ni se suma');
        $this->assertSame(1, $k['gasto_mxn']['campanas_otra_moneda']);
        $this->assertNull($k['costo_por_lead_mxn']['valor']);
    }

    public function test_redondeo_half_up_en_centavos(): void
    {
        // 10.05 / 2 = 5.025 → "5.03" (half-up; el redondeo bancario daría 5.02 y float puede dar 5.02)
        $this->campana(1, ['spent' => '10.05', 'status' => 'active', 'start_date' => $this->m->toDateString()]);
        $this->leads($this->m, ['new' => 2]);
        $this->assertSame('5.03', $this->resumen($this->m->format('Y-m'))->json('kpis.costo_por_lead_mxn.valor'));
    }

    public function test_gasto_en_cero_da_costo_por_lead_null(): void
    {
        $this->campana(1, ['spent' => '0.00', 'status' => 'active', 'start_date' => $this->m->toDateString()]);
        $this->leads($this->m, ['new' => 2]);
        $k = $this->resumen($this->m->format('Y-m'))->json('kpis');
        $this->assertSame('0.00', $k['gasto_mxn']['valor'], 'Hay campaña que califica: el gasto es 0.00, no null');
        $this->assertNull($k['costo_por_lead_mxn']['valor']);
    }

    public function test_montos_grandes_no_pierden_centavos(): void
    {
        $this->campana(1, ['spent' => '9999999999.99', 'status' => 'active', 'start_date' => $this->m->toDateString()]);
        $this->campana(1, ['spent' => '0.01', 'status' => 'paused', 'start_date' => $this->m->toDateString()]);
        $this->leads($this->m, ['new' => 3]);
        $k = $this->resumen($this->m->format('Y-m'))->json('kpis');
        $this->assertSame('10000000000.00', $k['gasto_mxn']['valor']);
        $this->assertSame('3333333333.33', $k['costo_por_lead_mxn']['valor']);
    }

    public function test_bordes_del_mes_en_hora_de_mexico(): void
    {
        $this->lead(1, 'new', $this->m->setTime(0, 0, 0));                         // primer segundo: cuenta
        $this->lead(1, 'new', $this->m->endOfMonth()->startOfSecond());             // último segundo: cuenta
        $this->lead(1, 'new', $this->m->subSecond());                               // último segundo de M-1: no
        $this->lead(1, 'new', $this->m->endOfMonth()->addSecond()->startOfSecond()); // primer segundo de M+1: no
        $this->assertSame(2, $this->resumen($this->m->format('Y-m'))->json('kpis.leads_mes.valor'));
    }

    public function test_sin_mes_usa_el_mes_actual_de_mexico(): void
    {
        $this->lead(1, 'won', $this->hoy());
        $r = $this->resumen()->assertOk();
        $r->assertJsonPath('periodo.mes', $this->hoy()->format('Y-m'));
        $this->assertSame(1, $r->json('kpis.leads_mes.valor'));
        $this->assertEquals(100.0, $r->json('kpis.conversion.valor'));
    }

    // ── ataques al parámetro mes ─────────────────────────────────────────────

    public function test_mes_invalido_da_422_con_errores_de_mes(): void
    {
        $futuro = $this->hoy()->addMonthNoOverflow()->format('Y-m');
        foreach (['', '2026-13', '2026-00', '2019-12', $futuro, '2026-1', '26-11', '<script>alert(1)</script>', "2026-11' OR 1=1 -- ", '2026-11;DROP TABLE marketing_leads', str_repeat('9', 10000)] as $mes) {
            $r = $this->como($this->adminA, 1)->getJson('/api/marketing/dashboard/resumen?mes=' . urlencode($mes));
            // Los textos con SQL los frena antes el WAF global (SqlInjectionProtection → 403 «Solicitud bloqueada»):
            // también es rechazo; lo que nunca puede pasar es 200 o 500.
            if ($r->status() === 403 && preg_match('/OR 1=1|DROP TABLE/i', $mes)) {
                $this->assertStringContainsString('bloqueada', (string) $r->getContent());
                continue;
            }
            $this->assertSame(422, $r->status(), "mes=" . substr($mes, 0, 40) . ' → ' . $r->status());
            $this->assertArrayHasKey('mes', $r->json('errors') ?? [], "mes=" . substr($mes, 0, 40));
        }
        $r = $this->como($this->adminA, 1)->getJson('/api/marketing/dashboard/resumen?mes[]=2026-11');
        $this->assertSame(422, $r->status(), 'mes como arreglo');
        $this->assertSame(422, $this->como($this->adminA, 1)->getJson('/api/marketing/dashboard/resumen?mes[a][b]=1')->status());
    }

    public function test_limites_validos_del_mes(): void
    {
        $this->resumen('2020-01')->assertOk()->assertJsonPath('periodo.mes_anterior', '2019-12');
        $this->resumen($this->hoy()->format('Y-m'))->assertOk();
    }

    // ── acceso (regla B de Henry) ────────────────────────────────────────────

    public function test_solo_admin_de_la_empresa_1_ve_el_resumen(): void
    {
        foreach ([$this->marketingA, $this->ventasA] as $u) {
            $this->como($u, 1)->getJson('/api/marketing/dashboard/resumen')->assertStatus(403);
            $this->como($u, 1)->getJson('/api/marketing/dashboard/campanas-activas')->assertStatus(403);
        }
        $this->como($this->sinEmpresa)->getJson('/api/marketing/dashboard/resumen')->assertStatus(403);
        $this->anonimo()->getJson('/api/marketing/dashboard/resumen')->assertStatus(401);
        $this->como($this->adminA, 1)->getJson('/api/marketing/dashboard/resumen')->assertOk();
    }

    public function test_con_sesion_pidiendo_otra_empresa_sigue_siendo_la_1(): void
    {
        $this->leads($this->m, ['new' => 4], $this->empresaB->id);
        $this->leads($this->m, ['new' => 1]);
        $r = $this->como($this->adminA, $this->empresaB->id)->getJson('/api/marketing/dashboard/resumen?mes=' . $this->m->format('Y-m'));
        $r->assertOk()->assertJsonPath('empresa_id', 1);
        $this->assertSame(1, $r->json('kpis.leads_mes.valor'), 'Nunca suma datos de otra empresa');
        foreach (['empresa_id=' . $this->empresaB->id, 'company_id=' . $this->empresaB->id] as $q) {
            $r = $this->como($this->adminA, 1)->getJson('/api/marketing/dashboard/resumen?mes=' . $this->m->format('Y-m') . '&' . $q);
            $this->assertSame(1, $r->json('kpis.leads_mes.valor'), "el company_id nunca sale del request ({$q})");
        }
    }
}
