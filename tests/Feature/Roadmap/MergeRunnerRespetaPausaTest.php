<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\JarvisIndiceService;
use App\Modules\Addons\Roadmap\Services\MergeRunner;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/**
 * Candado del item #9990643 (Fase B de #9990640): `MergeRunner::drain()` debe respetar el freno
 * de mano (`isPaused()`) en TODO camino que lo llame, no solo el que pasa por `SchedulerCommand`
 * (que ya chequeaba isPaused() ANTES de llamar a drain()). El hueco real era `circuito:merge-run`
 * (`MergeRunCommand`), que llamaba a `drain()` directo sin checar nada — con el freno puesto un
 * operador podía seguir mergeando a mano (incidente 2026-09-08). El fix mueve el guard DENTRO de
 * `drain()`, así que este test no necesita pasar por ningún comando concreto: basta con probar
 * `drain()` en sí, que es lo que TODOS los callers (presentes y futuros) terminan invocando.
 *
 * Usa `DB::table('settings')` directo (no `RoadmapCircuitoService::setPaused()`, que exige un
 * usuario autenticado con permiso `circuito.pause` — es la puerta de la Torre, no la de un test)
 * contra la base de PRUEBA (`megaisp_test`, ver phpunit.xml) — nunca toca el kill switch real del
 * circuito de dev/prod.
 */
class MergeRunnerRespetaPausaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_drain_no_mergea_ni_vacia_la_cola_si_esta_pausado(): void
    {
        $svc = new RoadmapCircuitoService();

        $item = RoadmapItem::create([
            'title' => 'Item de prueba #9990643 '.uniqid(),
            'status' => 'pending',
            'estado_aprobacion' => 'en_progreso',
            'branch' => 'rama-que-nunca-se-toca',
        ]);
        $svc->enqueueMerge($item->id, 'test', 'test');

        $this->ponerPausa();

        $runner = new MergeRunner($svc, new JarvisIndiceService());
        $res = $runner->drain();

        $this->assertSame([], $res, 'Con el freno puesto, drain() debe devolver [] (cola vacía desde el punto de vista del caller), no mergear nada.');
        $this->assertCount(1, $svc->mergeQueue(), 'El item encolado debe seguir en la cola: pausado no consume/pierde nada, solo no procesa.');

        $item->refresh();
        $this->assertSame('en_progreso', $item->estado_aprobacion, 'El item no debió tocarse (ni escalar ni marcarse mergeado) mientras el circuito está pausado.');
    }

    public function test_drain_si_procesa_la_cola_cuando_no_esta_pausado(): void
    {
        $svc = new RoadmapCircuitoService();

        $item = RoadmapItem::create([
            'title' => 'Item de prueba #9990643 sin-pausa '.uniqid(),
            'status' => 'pending',
            'estado_aprobacion' => 'en_progreso',
            'branch' => 'rama-inexistente-'.uniqid(),
        ]);
        $svc->enqueueMerge($item->id, 'test', 'test');

        $this->quitarPausa();

        $runner = new MergeRunner($svc, new JarvisIndiceService());
        $res = $runner->drain();

        $this->assertNotNull($res, 'Sin freno, drain() debe tomar el lock y procesar la cola.');
        $this->assertCount(1, $res, 'El único item encolado debió intentarse procesar (fallará por rama inexistente, pero eso confirma que SÍ llegó a performMerge, no que quedó bloqueado por pausa).');
        $this->assertSame(0, count($svc->mergeQueue()), 'La cola debe quedar vacía tras el drain (se consumió), a diferencia del caso pausado.');
    }

    private function ponerPausa(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => RoadmapCircuitoService::PAUSE_KEY],
            ['value' => '1']
        );
    }

    private function quitarPausa(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => RoadmapCircuitoService::PAUSE_KEY],
            ['value' => '0']
        );
    }
}
