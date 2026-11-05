<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Modules\Addons\Tickets\Services\TicketPlazoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketPlazoServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function ticket(string $type, int $prioridad, string $creado): Ticket
    {
        $t = new Ticket();
        $t->type = $type;
        $t->priority = $prioridad;
        $t->setRawAttributes(['type' => $type, 'priority' => $prioridad, 'created_at' => $creado], true);
        return $t;
    }

    public function test_sin_plazo_configurado_no_hay_compromiso(): void
    {
        DB::table('ticket_plazos')->delete();
        $this->assertNull((new TicketPlazoService)->estado($this->ticket('Incidente', 2, '2026-10-01 00:00:00')));
    }

    public function test_calcula_estado_y_prefiere_fila_especifica(): void
    {
        DB::table('ticket_plazos')->delete();
        DB::table('ticket_plazos')->insert([
            ['type' => null, 'priority' => null, 'horas_resolucion' => 100],
            ['type' => 'Incidente', 'priority' => 2, 'horas_resolucion' => 10],
        ]);
        $s = new TicketPlazoService;
        $t = $this->ticket('Incidente', 2, '2026-10-01 00:00:00');

        $this->assertSame('en_tiempo', $s->estado($t, Carbon::parse('2026-10-01 05:00:00'))['estado']);
        $this->assertSame('por_vencer', $s->estado($t, Carbon::parse('2026-10-01 08:30:00'))['estado']);
        $r = $s->estado($t, Carbon::parse('2026-10-01 12:00:00'));
        $this->assertSame('vencido', $r['estado']);
        $this->assertSame(10, $r['horas_plazo']);

        $otro = $this->ticket('Pregunta', 3, '2026-10-01 00:00:00');
        $this->assertSame(100, $s->estado($otro, Carbon::parse('2026-10-02 00:00:00'))['horas_plazo']);
    }
}
