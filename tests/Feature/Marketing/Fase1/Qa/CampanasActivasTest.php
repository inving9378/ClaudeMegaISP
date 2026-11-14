<?php

namespace Tests\Feature\Marketing\Fase1\Qa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase1\Qa\Concerns\EscenarioFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 1 — `GET /api/marketing/dashboard/campanas-activas` (contrato §2.4.2).
 * Forma exacta, orden por start_date desc, límite (default 5, 1–20), USD sin convertir, budget = 0,
 * leads distintos por atribución, y que nunca aparezca nada que no sea de la empresa 1.
 */
class CampanasActivasTest extends MarketingTestCase
{
    use EscenarioFase1;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
        $this->montarEscenarioFase1();
        DB::table('marketing_campaigns')->where('company_id', 1)->whereNull('deleted_at')->update(['deleted_at' => now()]);
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function pedir(string $q = '')
    {
        return $this->como($this->adminA, 1)->getJson('/api/marketing/dashboard/campanas-activas' . $q);
    }

    public function test_sin_campanas_activas_responde_vacio(): void
    {
        $this->campana(1, ['status' => 'draft']);
        $this->pedir()->assertOk()->assertExactJson(['ok' => true, 'total' => 0, 'data' => []]);
    }

    public function test_forma_exacta_de_una_campana(): void
    {
        $c = $this->campana(1, ['name' => 'Fibra 500 Megas QA1', 'type' => 'meta_ads', 'budget' => '5000.00', 'spent' => '1200.50',
            'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
        $l1 = $this->lead(1, 'new', now());
        $l2 = $this->lead(1, 'won', now());
        $this->atribucion($l1, $c, 'first_touch');
        $this->atribucion($l1, $c, 'last_touch'); // mismo lead dos veces → cuenta 1
        $this->atribucion($l2, $c);
        $borrado = $this->lead(1, 'new', now());
        $this->atribucion($borrado, $c);
        DB::table('marketing_leads')->where('id', $borrado->id)->update(['deleted_at' => now()]);

        $r = $this->pedir()->assertOk();
        $r->assertJsonPath('ok', true)->assertJsonPath('total', 1);
        $this->assertSame([
            'id' => $c->id, 'nombre' => 'Fibra 500 Megas QA1', 'tipo' => 'meta_ads', 'tipo_etiqueta' => 'Meta Ads',
            'estado' => 'active', 'moneda' => 'MXN', 'moneda_distinta' => false,
            'presupuesto' => '5000.00', 'gastado' => '1200.50', 'avance_presupuesto' => 24.0,
            'inicio' => '2026-11-01', 'fin' => '2026-11-30', 'leads' => 2,
        ], $this->normalizar($r->json('data.0')));
        $this->assertMatchesRegularExpression('/"presupuesto":"5000\.00","gastado":"1200\.50"/', (string) $r->getContent(), 'Dinero como cadena');
    }

    /** avance_presupuesto puede llegar como 24 o 24.0 en JSON; se compara como float. */
    private function normalizar(array $fila): array
    {
        if (isset($fila['avance_presupuesto'])) {
            $fila['avance_presupuesto'] = (float) $fila['avance_presupuesto'];
        }

        return $fila;
    }

    public function test_etiquetas_de_tipo(): void
    {
        $esperadas = ['meta_ads' => 'Meta Ads', 'google_ads' => 'Google Ads', 'organic' => 'Orgánica', 'email_blast' => 'Correo masivo',
            'sms_blast' => 'SMS masivo', 'voice_blast' => 'Llamadas', 'mixed' => 'Mixta', 'manual' => 'Manual'];
        foreach (array_keys($esperadas) as $i => $tipo) {
            $this->campana(1, ['type' => $tipo, 'start_date' => now()->subDays($i)->toDateString()]);
        }
        $data = $this->pedir('?limite=20')->assertOk()->json('data');
        $this->assertSame($esperadas, array_column($data, 'tipo_etiqueta', 'tipo'));
    }

    public function test_orden_por_inicio_descendente_limite_y_total(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->campana(1, ['name' => "C{$i}", 'start_date' => now()->subDays(10 - $i)->toDateString()]);
        }
        $this->campana(1, ['name' => 'Pausada', 'status' => 'paused']);
        $this->campana($this->empresaB->id, ['name' => 'De otra empresa', 'start_date' => now()->toDateString()]);

        $r = $this->pedir()->assertOk();
        $this->assertSame(7, $r->json('total'), 'total = todas las activas de la empresa 1');
        $this->assertSame(['C6', 'C5', 'C4', 'C3', 'C2'], array_column($r->json('data'), 'nombre'), 'default 5, por start_date desc');
        $this->assertCount(1, $this->pedir('?limite=1')->json('data'));
        $this->assertCount(7, $this->pedir('?limite=20')->json('data'));
        $this->assertStringNotContainsString('De otra empresa', (string) $this->pedir('?limite=20')->getContent());
    }

    public function test_usd_no_se_convierte_y_presupuesto_cero_da_avance_null(): void
    {
        $this->campana(1, ['name' => 'USD', 'currency' => 'USD', 'budget' => '100.00', 'spent' => '33.33', 'start_date' => now()->toDateString()]);
        $this->campana(1, ['name' => 'Cero', 'budget' => '0.00', 'spent' => '10.00', 'start_date' => now()->subDay()->toDateString()]);
        $data = collect($this->pedir()->assertOk()->json('data'))->keyBy('nombre');

        $this->assertSame('USD', $data['USD']['moneda']);
        $this->assertTrue($data['USD']['moneda_distinta']);
        $this->assertSame('33.33', $data['USD']['gastado'], 'Se muestra con su moneda, sin convertir');
        $this->assertEquals(33.3, $data['USD']['avance_presupuesto']);
        $this->assertNull($data['Cero']['avance_presupuesto'], 'budget = 0 → null');
        $this->assertFalse($data['Cero']['moneda_distinta']);
    }

    public function test_limite_invalido_da_422(): void
    {
        foreach (['0', '21', '-1', 'abc', '5.5', '<script>', '99999999999999999999'] as $v) {
            $r = $this->pedir('?limite=' . urlencode($v));
            $this->assertSame(422, $r->status(), "limite={$v} → {$r->status()}");
        }
        $this->assertSame(422, $this->pedir('?limite[]=5')->status());
        // Vacío: el contrato no lo fija (Laravel lo vuelve null). Vale default o 422, nunca 500.
        $this->assertContains($this->pedir('?limite=')->status(), [200, 422]);
    }

    public function test_solo_admin_de_la_empresa_1(): void
    {
        $this->como($this->marketingA, 1)->getJson('/api/marketing/dashboard/campanas-activas')->assertStatus(403);
        $this->como($this->ventasA, 1)->getJson('/api/marketing/dashboard/campanas-activas')->assertStatus(403);
        $this->anonimo()->getJson('/api/marketing/dashboard/campanas-activas')->assertStatus(401);
    }
}
