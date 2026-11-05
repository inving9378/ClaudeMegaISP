<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Controllers\RoadmapController;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\MergesPendientesService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/** #1114: «esperan merge» de Integración = `total` de MergesPendientesService (misma regla que la Panorama). */
class IntegracionEsperanMergeMismaReglaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_el_conteo_de_integracion_es_el_total_del_servicio(): void
    {
        $mk = fn (array $o) => RoadmapItem::create($o + ['title' => 'T1114 '.uniqid(), 'status' => 'pending',
            'nivel_riesgo' => 'A', 'esperando_merge_irving' => true, 'branch' => 'circuito/item-t1114']);
        $mk(['estado_aprobacion' => 'en_progreso']);
        $mk(['estado_aprobacion' => 'en_progreso', 'branch' => null]);          // cuenta, sin rama
        $mk(['estado_aprobacion' => 'en_progreso', 'merge_commit' => 'abc123']); // ya mergeado: no cuenta
        $mk(['estado_aprobacion' => 'completado']);                              // cerrado: no cuenta

        \Illuminate\Support\Facades\Gate::before(fn () => true);
        $this->actingAs(new User());
        $data = app(RoadmapController::class)->integracion()->getData(true);

        $total = app(MergesPendientesService::class)->listar()['total'];
        $this->assertSame($total, $data['esperan_merge_count']);
        $this->assertGreaterThanOrEqual(1, $data['esperan_sin_rama_count']);
    }
}
