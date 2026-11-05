<?php

namespace Tests\Feature\Unificacion;

use App\Models\Task;
use App\Models\Ticket;
use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/**
 * UNI-T G3b (#1087): completar una orden no cierra el ticket salvo con el interruptor encendido.
 * Corre SOLO contra megaisp_test y deshace todo con DatabaseTransactions.
 */
class CierreOrdenTicketG3bTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('megaisp_test', DB::connection()->getDatabaseName(), 'Solo en megaisp_test');
    }

    private function ticket(): Ticket
    {
        return Ticket::create([
            'topic' => 'G3b '.uniqid(), 'customer_lead' => 0, 'priority' => 1, 'estado' => 'Nuevo',
            'reporter' => 'x', 'reporter_id' => 0, 'date_time' => now()->format('Y-m-d\TH:i'),
        ]);
    }

    private function orden(Ticket $t, string $status): Task
    {
        $task = new Task();
        $task->forceFill(['title' => 'G3b '.uniqid(), 'status' => $status, 'ticket_id' => $t->id]);
        $task->saveQuietly();
        return $task;
    }

    private function cerrar(Task $t): void
    {
        $m = new \ReflectionMethod(OrdenTrabajoUnifiedService::class, 'cerrarTicketSiCorresponde');
        $m->setAccessible(true);
        $m->invoke(app(OrdenTrabajoUnifiedService::class), $t);
    }

    public function test_apagado_completar_orden_no_cierra_ticket(): void
    {
        config(['unificacion.interruptores.cierre_orden_cierra_ticket_g3b' => false]);
        $ticket = $this->ticket();
        $this->cerrar($this->orden($ticket, 'Done'));
        $this->assertSame('Nuevo', $ticket->fresh()->estado);
    }

    public function test_encendido_cierra_solo_si_no_quedan_ordenes_abiertas(): void
    {
        config(['unificacion.interruptores.cierre_orden_cierra_ticket_g3b' => true]);
        $ticket = $this->ticket();
        $a = $this->orden($ticket, 'Done');
        $this->orden($ticket, 'InProgress');
        $this->cerrar($a);
        $this->assertSame('Nuevo', $ticket->fresh()->estado);

        $ticket2 = $this->ticket();
        $this->cerrar($this->orden($ticket2, 'Done'));
        $this->assertSame('Cerrado', $ticket2->fresh()->estado);
    }
}
