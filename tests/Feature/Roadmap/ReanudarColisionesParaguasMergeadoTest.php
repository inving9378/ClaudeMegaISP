<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/**
 * Item #9990567 — CANDADO de regresión: `reanudarColisionesResueltas()` no liberaba el candado
 * #438 cuando el item GANADOR de la colisión quedaba parked como paraguas (descompuesto en
 * sub-items, guard de `RoadmapItem::save` bloque 2b) con `merge_commit` ya poblado — su código
 * ya está en main, pero `estado_aprobacion` se queda en 'aprobado_irving' hasta que TODOS sus
 * sub-items cierren, lo que puede tardar días. La condición vieja solo consideraba resuelta la
 * colisión si el ganador llegaba a completado/cancelado/rechazado, así que el perdedor quedaba
 * bloqueado indefinidamente (caso real: #9990562 vs #9990494 durante toda la tarde del 2026-09-07).
 *
 * USA `DatabaseTransactions` + `CreatesApplication` (rollback al terminar), mismo patrón que
 * `CierreHuecoRebotaTest`/`StatusResincronizaAlEscalarTest` — no ensucia la BD compartida.
 */
class ReanudarColisionesParaguasMergeadoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_ganador_paraguas_con_merge_commit_libera_al_perdedor_aunque_siga_aprobado_irving(): void
    {
        $ganador = RoadmapItem::create([
            'title'             => 'Ganador paraguas ya mergeado '.uniqid(),
            'estado_aprobacion' => 'aprobado_irving',
            'status'            => 'pending',
            'merge_commit'      => 'abc1234',
            'excluir_pool_automatico' => true,
        ]);

        $perdedor = RoadmapItem::create([
            'title'                => 'Perdedor bloqueado por candado #438 '.uniqid(),
            'estado_aprobacion'    => 'en_progreso',
            'status'               => 'pending',
            'colision_pausada_por' => $ganador->id,
            'colision_pausada_at'  => now(),
            'worker_sid'           => 'wt-9',
        ]);
        // `estado_previo_claim` no está en $fillable a propósito (solo se escribe vía el UPDATE
        // atómico del reclamo real, ver RoadmapCircuitoService.php:2358) — se asigna directo para
        // reproducir ese mismo estado en el fixture.
        $perdedor->estado_previo_claim = 'aprobado_revisor';
        $perdedor->save();

        $reanudados = (new RoadmapCircuitoService())->reanudarColisionesResueltas();

        $this->assertContains($perdedor->id, $reanudados);

        $fresco = $perdedor->fresh();
        $this->assertNull($fresco->colision_pausada_por, 'El candado #438 debe liberarse aunque el ganador siga en aprobado_irving.');
        $this->assertNull($fresco->colision_pausada_at);
        $this->assertNull($fresco->worker_sid);
        $this->assertSame('aprobado_revisor', $fresco->estado_aprobacion, 'Debe regresar al estado aprobado previo al reclamo.');

        // El ganador NO se toca: sigue parked como paraguas exactamente igual.
        $this->assertSame('aprobado_irving', $ganador->fresh()->estado_aprobacion);
    }

    public function test_ganador_sin_merge_commit_y_sin_estado_terminal_no_libera_el_candado(): void
    {
        $ganador = RoadmapItem::create([
            'title'             => 'Ganador todavía en vuelo '.uniqid(),
            'estado_aprobacion' => 'en_progreso',
            'status'            => 'pending',
        ]);

        $perdedor = RoadmapItem::create([
            'title'                => 'Perdedor legítimamente bloqueado '.uniqid(),
            'estado_aprobacion'    => 'en_progreso',
            'status'               => 'pending',
            'estado_previo_claim'  => 'aprobado_revisor',
            'colision_pausada_por' => $ganador->id,
            'colision_pausada_at'  => now(),
            'worker_sid'           => 'wt-9',
        ]);

        $reanudados = (new RoadmapCircuitoService())->reanudarColisionesResueltas();

        $this->assertNotContains($perdedor->id, $reanudados);
        $this->assertNotNull($perdedor->fresh()->colision_pausada_por, 'Una colisión real (ganador sin merge_commit ni estado terminal) sigue bloqueando.');
    }
}
