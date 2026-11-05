<?php

namespace Tests\Feature\Modules\Talento;

use App\Models\Referrals\Referral;
use App\Models\Ticket;
use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use Database\Seeders\UniPruebaSeeder;
use Tests\TestCase;

class UniPruebaVentasTest extends TestCase
{
    public function test_siembra_ticket_venta_referido_y_liquidaciones(): void
    {
        $seeder = new UniPruebaSeeder();
        $colabs = $seeder->perfiles();
        $r = $seeder->ventas($colabs);

        $this->assertSame('Nuevo', $r['ticket']->estado);
        $this->assertSame($colabs['vendedor']->user_id, $r['vendedor']->user_id);
        $this->assertSame('pending_threshold', $r['referral']->status);
        $this->assertSame(['draft', 'closed'], [$r['abierta']->status, $r['cerrada']->status]);

        // Idempotente
        $seeder->ventas($seeder->perfiles());
        $this->assertSame(1, Ticket::where('topic', 'UNI-PRUEBA ticket')->count());
        $this->assertSame(1, Referral::where('embajador_id', $r['referral']->embajador_id)->count());
        $this->assertSame(2, TalentoLiquidation::where('colaborador_id', $colabs['tecnico2']->id)->count());
    }
}
