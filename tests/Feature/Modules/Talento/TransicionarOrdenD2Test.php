<?php

namespace Tests\Feature\Modules\Talento;

use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use App\Modules\Addons\Talento\Services\TalentoOrderEventRecorder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransicionarOrdenD2Test extends TestCase
{
    use DatabaseTransactions;

    public function test_apagado_no_registra_nada(): void
    {
        config(['unificacion.interruptores.transiciones_d2' => false]);
        app(OrdenTrabajoUnifiedService::class)->transicionar('task', 990101, 'order.started', 'pending', 'in_progress', 1);
        $this->assertCount(0, TalentoOrderEventRecorder::deOrden('task', 990101));
    }

    public function test_encendido_registra_evento_y_quien_valido(): void
    {
        config(['unificacion.interruptores.transiciones_d2' => true]);
        $svc = app(OrdenTrabajoUnifiedService::class);
        $svc->transicionar('task', 990102, 'order.validated', 'completed', 'validated', 7, null, ['validated_by']);
        $ev = TalentoOrderEventRecorder::deOrden('task', 990102);
        $this->assertCount(1, $ev);
        $this->assertSame(7, (int) $ev->first()->actor_id);
    }

    public function test_columnas_de_actor_existen(): void
    {
        foreach (['tasks', 'talento_work_orders'] as $t) {
            foreach (['assigned_by', 'completed_by', 'validated_by'] as $c) {
                $this->assertTrue(\Schema::hasColumn($t, $c), "$t.$c");
            }
        }
    }
}
