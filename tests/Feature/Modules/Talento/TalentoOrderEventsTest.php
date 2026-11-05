<?php

namespace Tests\Feature\Modules\Talento;

use App\Modules\Addons\Talento\Models\TalentoOrderEvent;
use App\Modules\Addons\Talento\Services\TalentoOrderEventRecorder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\TestCase;

class TalentoOrderEventsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_alta_y_consulta_por_orden_en_orden_cronologico(): void
    {
        TalentoOrderEventRecorder::registrar('task', 990001, 'order.created', null, 'pending', 1, null, ['a' => 1]);
        TalentoOrderEventRecorder::registrar('task', 990001, 'order.started', 'pending', 'in_progress');
        TalentoOrderEventRecorder::registrar('task', 990002, 'order.created');

        $ev = TalentoOrderEventRecorder::deOrden('task', 990001);
        $this->assertCount(2, $ev);
        $this->assertSame(['order.created', 'order.started'], $ev->pluck('evento')->all());
        $this->assertSame(['a' => 1], $ev->first()->datos);
    }

    public function test_editar_un_evento_falla(): void
    {
        $e = TalentoOrderEventRecorder::registrar('task', 990003, 'order.created');
        $this->expectException(LogicException::class);
        $e->update(['evento' => 'otro']);
    }

    public function test_borrar_un_evento_falla(): void
    {
        $e = TalentoOrderEventRecorder::registrar('task', 990004, 'order.created');
        try {
            $e->delete();
            $this->fail('El borrado debía fallar');
        } catch (LogicException $x) {
            $this->assertSame(1, TalentoOrderEvent::where('orden_id', 990004)->count());
        }
    }
}
