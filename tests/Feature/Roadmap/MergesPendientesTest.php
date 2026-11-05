<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\MergesPendientesService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/**
 * «Esperan tu merge» en vivo: una sola regla para la tarjeta de la Panorama, el digest y Jarvis.
 */
class MergesPendientesTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_solo_abiertos_sin_merge_y_ordenados_por_lo_que_liberan(): void
    {
        $espera   = $this->item(['esperando_merge_irving' => 1]);
        $libera   = $this->item(['esperando_merge_irving' => 1]);
        $this->item(['depende_de' => json_encode([$libera])]);
        $this->item(['depende_de' => json_encode([$libera])]);
        $mergeado = $this->item(['esperando_merge_irving' => 1, 'merge_commit' => 'abc1234']);
        $archivo  = $this->item(['esperando_merge_irving' => 1, 'archivado_at' => now()]);
        $cerrado  = $this->item(['esperando_merge_irving' => 1, 'estado_aprobacion' => 'completado']);

        $r   = app(MergesPendientesService::class)->listar();
        $ids = array_column($r['items'], 'id');

        $this->assertContains($espera, $ids);
        $this->assertContains($libera, $ids);
        foreach ([$mergeado, $archivo, $cerrado] as $fuera) {
            $this->assertNotContains($fuera, $ids);
        }
        $this->assertLessThan(array_search($espera, $ids), array_search($libera, $ids));
        $this->assertSame(2, collect($r['items'])->firstWhere('id', $libera)['desbloquea']);
        $this->assertSame(count($ids), $r['total']);
        $this->assertCount(1, app(MergesPendientesService::class)->listar(1)['items']);
    }

    private function item(array $campos): int
    {
        $item = RoadmapItem::create([
            'title' => 'Merge de ejemplo ' . uniqid(),
            'status' => 'pending',
            'estado_aprobacion' => 'aprobado_irving',
            'priority' => 'media',
        ]);
        // Directo a la tabla: estos campos los pone el circuito, no el alta.
        DB::table('roadmap_items')->where('id', $item->id)->update($campos);

        return $item->id;
    }
}
