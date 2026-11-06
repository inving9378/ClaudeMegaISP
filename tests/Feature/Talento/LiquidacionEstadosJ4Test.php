<?php

namespace Tests\Feature\Talento;

use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use App\Modules\Addons\Talento\Services\LiquidationStateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

/** UNI-T J4 F3 (#982): transiciones válidas y prohibidas de la liquidación (draft→closed→paid) y sus eventos. */
class LiquidacionEstadosJ4Test extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['unificacion.interruptores.liquidacion_estados_j4' => true, 'unificacion.ajustes_semana_cerrada.activo' => false]);
    }

    private int $n = 0;

    private function liquidacion(string $status = 'draft'): TalentoLiquidation
    {
        $fila = ['status' => $status, 'period_start' => now()->addYears(5)->addDays(++$this->n * 10)->toDateString(), 'period_end' => now()->addYears(5)->addDays($this->n * 10 + 7)->toDateString(),
            'created_at' => now(), 'updated_at' => now()];
        foreach (Schema::getColumns('talento_liquidations') as $c) {
            if (! $c['nullable'] && $c['default'] === null && ! $c['auto_increment'] && ! array_key_exists($c['name'], $fila)) {
                $t = $c['type_name'];
                $fila[$c['name']] = str_contains($t, 'int') || str_contains($t, 'decimal') ? 0 : (in_array($t, ['datetime', 'timestamp', 'date']) ? now() : 'x');
            }
        }
        Schema::disableForeignKeyConstraints();
        $id = DB::table('talento_liquidations')->insertGetId($fila);
        Schema::enableForeignKeyConstraints();
        return TalentoLiquidation::findOrFail($id);
    }

    private function svc(): LiquidationStateService
    {
        return app(LiquidationStateService::class);
    }

    private function eventos(int $id)
    {
        return DB::table('talento_liquidation_events')->where('liquidation_id', $id)->orderBy('id')->get();
    }

    private function rechaza(callable $f, string $fragmento): void
    {
        try {
            $f();
            $this->fail('debía rechazar');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($fragmento, $e->getMessage());
        }
    }

    public function test_flujo_valido_draft_closed_paid_con_eventos(): void
    {
        $liq = $this->liquidacion();
        $cerrada = $this->svc()->cerrar($liq, 11);
        $this->assertSame('closed', $cerrada->status);
        $this->assertNotNull($cerrada->closed_at);

        $pagada = $this->svc()->pagar($cerrada, 22, 'SPEI-123');
        $this->assertSame('paid', $pagada->status);
        $this->assertNotNull($pagada->paid_at);
        $this->assertSame('SPEI-123', $pagada->payment_reference);

        $ev = $this->eventos($liq->id);
        $this->assertCount(2, $ev);
        $this->assertSame(['cierre', 'draft', 'closed', 11, null], [$ev[0]->tipo, $ev[0]->from_status, $ev[0]->to_status, $ev[0]->user_id, $ev[0]->referencia]);
        $this->assertSame(['pago', 'closed', 'paid', 22, 'SPEI-123'], [$ev[1]->tipo, $ev[1]->from_status, $ev[1]->to_status, $ev[1]->user_id, $ev[1]->referencia]);
        $this->assertNotNull($ev[0]->created_at);
    }

    public function test_no_se_puede_pagar_sin_cerrar(): void
    {
        $liq = $this->liquidacion('draft');
        $this->rechaza(fn () => $this->svc()->pagar($liq, 1), 'Solo se puede pagar una liquidación cerrada');
        $this->assertSame('draft', $liq->fresh()->status);
        $this->assertCount(0, $this->eventos($liq->id));
    }

    public function test_no_se_reescribe_una_pagada(): void
    {
        $liq = $this->liquidacion('closed');
        $this->svc()->pagar($liq, 1, 'REF-1');
        $this->rechaza(fn () => $this->svc()->pagar($liq, 2, 'REF-2'), 'ya está pagada');
        $this->rechaza(fn () => $this->svc()->cerrar($liq->fresh(), 2), 'ya está pagada');
        $f = $liq->fresh();
        $this->assertSame('REF-1', $f->payment_reference);
        $this->assertSame(1, (int) $f->paid_by);
        $this->assertCount(1, $this->eventos($liq->id));
    }

    public function test_no_se_cierra_dos_veces_ni_se_retrocede(): void
    {
        $liq = $this->liquidacion();
        $cerrada = $this->svc()->cerrar($liq, 1);
        $this->rechaza(fn () => $this->svc()->cerrar($cerrada, 1), 'ya está cerrada');
        $this->assertSame('closed', $liq->fresh()->status);
        $this->assertCount(1, $this->eventos($liq->id));
    }

    public function test_reabrir_esta_prohibido_en_cualquier_estado(): void
    {
        foreach (['draft', 'closed', 'paid'] as $st) {
            $liq = $this->liquidacion($st);
            $this->rechaza(fn () => $this->svc()->reabrir($liq), 'no se reabre');
            $this->assertSame($st, $liq->fresh()->status);
        }
    }

    public function test_interruptor_apagado_bloquea_las_transiciones(): void
    {
        config(['unificacion.interruptores.liquidacion_estados_j4' => false]);
        $liq = $this->liquidacion();
        $this->rechaza(fn () => $this->svc()->cerrar($liq, 1), 'apagados');
        $this->assertSame('draft', $liq->fresh()->status);
        $this->assertCount(0, $this->eventos($liq->id));
    }
}
