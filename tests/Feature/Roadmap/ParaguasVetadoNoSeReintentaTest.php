<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\AvanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\CreatesApplication;

/**
 * Un paraguas cuyo cierre vetó un guard del modelo no se reintenta cada minuto: cada intento
 * reescribía la fila con su log creciendo y llenó el disco de dev vía binlog (2026-11-09).
 */
class ParaguasVetadoNoSeReintentaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::fake();
    }

    /** Paraguas nivel C con rama y sin merge (el guard de nivel C lo regresa a aprobado_irving) con su único hijo cerrado. */
    private function paraguasVetable(): RoadmapItem
    {
        $p = RoadmapItem::create(['title' => 'Paraguas ' . uniqid(), 'status' => 'pending', 'estado_aprobacion' => 'aprobado_irving']);
        $p->forceFill([
            'estado_aprobacion' => 'aprobado_irving', 'nivel_riesgo' => 'C', 'branch' => 'circuito/item-prueba-paraguas',
            'merge_commit' => null, 'worker_sid' => null, 'archivado_at' => null,
        ])->saveQuietly();

        $h = RoadmapItem::create(['title' => 'Hijo ' . uniqid(), 'status' => 'pending', 'estado_aprobacion' => 'aprobado_irving']);
        $h->forceFill(['origen_item_id' => $p->id, 'estado_aprobacion' => 'completado', 'status' => 'done'])->saveQuietly();

        return $p->fresh();
    }

    private function intentos(RoadmapItem $p): int
    {
        return collect($p->fresh()->log ?: [])
            ->where('por', 'jarvis-avance')->where('evento', 'paraguas_cerrado')->count();
    }

    public function test_cierre_vetado_no_se_reintenta_en_la_siguiente_pasada(): void
    {
        $p = $this->paraguasVetable();
        $avance = app(AvanceService::class);

        $this->assertContains($p->id, $avance->cerrarParaguasConHijosCerrados());
        $this->assertSame('aprobado_irving', $p->fresh()->estado_aprobacion, 'el guard de nivel C sin merge veta el cierre');
        $this->assertSame(1, $this->intentos($p));

        $this->assertNotContains($p->id, $avance->cerrarParaguasConHijosCerrados());
        $this->assertSame(1, $this->intentos($p), 'la segunda pasada no vuelve a escribir la fila');
    }

    public function test_pasada_la_espera_se_reintenta(): void
    {
        $p = $this->paraguasVetable();
        $avance = app(AvanceService::class);
        $avance->cerrarParaguasConHijosCerrados();

        $this->travel(AvanceService::HORAS_REINTENTO_PARAGUAS + 1)->hours();

        $this->assertContains($p->id, $avance->cerrarParaguasConHijosCerrados());
        $this->assertSame(2, $this->intentos($p));
    }
}
