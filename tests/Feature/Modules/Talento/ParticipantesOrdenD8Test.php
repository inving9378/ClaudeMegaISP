<?php

namespace Tests\Feature\Modules\Talento;

use App\Modules\Addons\Talento\Services\OrderParticipantsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParticipantesOrdenD8Test extends TestCase
{
    use DatabaseTransactions;

    public function test_apagado_no_devuelve_ni_escribe(): void
    {
        config(['unificacion.interruptores.participantes_d8' => false]);
        $svc = app(OrderParticipantsService::class);
        $svc->fijar('task', 990201, 1, [['user_id' => 2]]);
        $this->assertSame([], $svc->listar('task', 990201));
        $this->assertSame(0, DB::table('talento_order_participants')->where('orden_id', 990201)->count());
    }

    public function test_encendido_principal_primero_y_aporte(): void
    {
        config(['unificacion.interruptores.participantes_d8' => true]);
        $svc = app(OrderParticipantsService::class);
        $svc->fijar('work_order', 990202, 5, [['user_id' => 6, 'aporte' => 'tendido'], ['user_id' => 5]]);
        $l = $svc->listar('work_order', 990202);
        $this->assertCount(2, $l);
        $this->assertSame('principal', $l[0]['rol']);
        $this->assertSame(5, (int) $l[0]['user_id']);
        $this->assertSame('tendido', $l[1]['aporte']);
    }
}
