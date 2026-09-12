<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/**
 * CANDADO de regresión — item #9990890 (CIRC-07): la pestaña "Hoja de ruta" (Kanban por `status`,
 * filtro "Todos") cargaba con `GET /api/roadmap/items?vista=backlog`. Ese scope
 * (`RoadmapItem::scopeBacklog()`) es el INTAKE del #432 — SOLO `nivel_riesgo IS NULL` — así que
 * cualquier item YA triado (la inmensa mayoría, 1580+ de 1635 en dev) desaparecía de la vista y las
 * 4 tarjetas (en_progreso/pendientes/hechos/cancelados) quedaban en cero pese a haber datos.
 *
 * El fix (`RoadmapTab.vue::load()`) quitó `?vista=backlog`: sin ese query param,
 * `RoadmapController::index()` usa `RoadmapItem::ordered()` (solo ORDER BY, sin WHERE) — el mismo
 * universo que cuenta `RoadmapCircuitoService::resumen()['total']` (= `RoadmapItem::count()`), que
 * es lo que muestra la pestaña Panorama. Este test pin-ea esa igualdad para que un futuro cambio no
 * vuelva a filtrar por accidente la rama sin-vista del endpoint.
 */
class HojaDeRutaSinVistaBacklogTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_scope_ordered_sin_vista_devuelve_el_mismo_universo_que_el_total_del_panorama(): void
    {
        // Items YA triados y en distintos estados — justo lo que `scopeBacklog()` excluye por
        // diseño (nivel_riesgo NOT NULL) y lo que la pestaña "Hoja de ruta" SÍ debe listar.
        $triados = [
            RoadmapItem::create(['title' => 'CIRC-07 test en_progreso '.uniqid(), 'status' => 'in_progress', 'estado_aprobacion' => 'en_progreso', 'nivel_riesgo' => 'A']),
            RoadmapItem::create(['title' => 'CIRC-07 test pending '.uniqid(), 'status' => 'pending', 'estado_aprobacion' => 'aprobado_irving', 'nivel_riesgo' => 'B']),
            RoadmapItem::create(['title' => 'CIRC-07 test done '.uniqid(), 'status' => 'done', 'estado_aprobacion' => 'completado', 'nivel_riesgo' => 'A']),
            RoadmapItem::create(['title' => 'CIRC-07 test cancelled '.uniqid(), 'status' => 'cancelled', 'estado_aprobacion' => 'cancelado', 'nivel_riesgo' => 'C']),
        ];

        $totalUniverso = RoadmapItem::count();

        // Lo que usa el endpoint sin `?vista=backlog` (rama arreglada del controller).
        $this->assertSame(
            $totalUniverso,
            RoadmapItem::ordered()->count(),
            '`RoadmapItem::ordered()` (usado sin ?vista=backlog) debe devolver el UNIVERSO COMPLETO, igual que el total del Panorama.'
        );

        // Confirma que los 4 items recién creados —representativos de las 4 tarjetas del Kanban—
        // están en ese universo (no fueron excluidos por ningún WHERE de scopeOrdered).
        $idsOrdered = RoadmapItem::ordered()->pluck('id');
        foreach ($triados as $item) {
            $this->assertTrue(
                $idsOrdered->contains($item->id),
                "El item {$item->id} (ya triado, nivel_riesgo={$item->nivel_riesgo}) debe aparecer en la Hoja de ruta sin vista=backlog."
            );
        }

        // Prueba negativa: `scopeBacklog()` SÍ los excluye (es el bug original) — documenta por qué
        // usarlo en esta pestaña estaba mal, sin tocar ese scope (lo sigue usando el KPI "sin
        // clasificar" del Panorama).
        $idsBacklog = RoadmapItem::backlog()->pluck('id');
        foreach ($triados as $item) {
            $this->assertFalse(
                $idsBacklog->contains($item->id),
                "El item {$item->id} SÍ tiene nivel_riesgo → scopeBacklog() (intake) debe seguir excluyéndolo; si esto falla, el bug de CIRC-07 pudo reaparecer al revés."
            );
        }
    }
}
