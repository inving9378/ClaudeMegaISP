<?php

namespace Tests\Feature\Talento;

use App\Modules\Addons\Talento\Services\WarrantyReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\CreatesApplication;

/** UNI-T L2 (#928): ciclo de revisión de garantías; sin resolución no hay efecto económico. */
class GarantiaRevisionL2Test extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function evento(int $ticket, array $participantes): int
    {
        return DB::table('talento_warranty_events')->insertGetId([
            'ticket_id' => $ticket, 'client_id' => 777100, 'orden_origen' => 'task', 'orden_id' => 888100, 'orden_ref' => 'T-888100',
            'participantes' => json_encode($participantes), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_sin_resolucion_no_hay_efecto_economico(): void
    {
        config(['unificacion.interruptores.garantia_l2' => true]);
        $s = app(WarrantyReviewService::class);
        $ev = $this->evento(991001, [['user_id' => 501, 'rol' => 'principal']]);
        $this->assertFalse($s->puedeAfectarEconomia($ev));
        $r = $s->abrir($ev);
        $this->assertSame('abierta', $r->estado);
        $this->assertFalse($s->puedeAfectarEconomia($ev));
        $s->tomar($r->id, 900);
        $this->assertFalse($s->puedeAfectarEconomia($ev));
    }

    public function test_revisor_distinto_del_tecnico_original(): void
    {
        $s = app(WarrantyReviewService::class);
        $r = $s->abrir($this->evento(991002, [['user_id' => 501, 'rol' => 'principal']]));
        $this->expectException(InvalidArgumentException::class);
        $s->tomar($r->id, 501);
    }

    public function test_ciclo_completo_y_efecto_segun_resolucion(): void
    {
        config(['unificacion.interruptores.garantia_l2' => true]);
        $s = app(WarrantyReviewService::class);
        $causa = DB::table('talento_warranty_causes')->value('id');
        $ev = $this->evento(991003, [['user_id' => 501]]);
        $r = $s->abrir($ev);
        $s->tomar($r->id, 900);
        $out = $s->resolver($r->id, $causa, 'trabajo_original', 'atribuida_trabajo_original', 'x');
        $this->assertSame('resuelta', $out->estado);
        $this->assertTrue($s->puedeAfectarEconomia($ev));

        $ev2 = $this->evento(991004, [['user_id' => 501]]);
        $r2 = $s->abrir($ev2);
        $s->tomar($r2->id, 900);
        $s->resolver($r2->id, $causa, 'cliente', 'sin_efecto');
        $this->assertFalse($s->puedeAfectarEconomia($ev2));
    }

    public function test_no_se_resuelve_sin_revision_y_apagado_conserva_comportamiento(): void
    {
        $s = app(WarrantyReviewService::class);
        $r = $s->abrir($this->evento(991005, [['user_id' => 501]]));
        try {
            $s->resolver($r->id, 1, 'ninguna', 'sin_efecto');
            $this->fail('debía rechazar');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue(true);
        }
        config(['unificacion.interruptores.garantia_l2' => false]);
        $this->assertTrue($s->puedeAfectarEconomia(1));
    }
}
