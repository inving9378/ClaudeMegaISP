<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/**
 * Item #9990348 — CANDADO de regresión del ciclo real que dejó 6 items vivos invisibles para el
 * pool el 2026-09-04: un item llega a `completado` (el guard #420 sincroniza `status='done'`), y
 * DESPUÉS algo lo devuelve a un estado vivo (timeout, conflicto de merge, reclamo huérfano
 * liberado) sin que nadie resincronice `status`. Sin el guard inverso (`RoadmapItem::boot()`,
 * hook `saving` junto al #420), el item queda `status='done'` + `estado_aprobacion` vivo —
 * `scopeDespachable()` lo excluye por el `whereNotIn('status', ['done'])` aunque esté autorizado.
 *
 * USA `DatabaseTransactions` + `CreatesApplication` (rollback al terminar), mismo patrón que
 * `DependenciaGateDespachoTest`/`SubItemCommandDependeDeTest` — no ensucia la BD compartida.
 */
class StatusResincronizaAlEscalarTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_reescalar_un_item_completado_resincroniza_status_y_lo_deja_despachable(): void
    {
        // `aprobado_irving` pasa TODAS las cláusulas de `scopeDespachable()` sin importar
        // nivel_riesgo/política de la Torre — el único freno bajo prueba es el de `status`.
        $item = RoadmapItem::create([
            'title'             => 'Item de prueba ciclo completado→escalado '.uniqid(),
            'estado_aprobacion' => 'aprobado_irving',
            'status'            => 'pending',
        ]);

        // Llega a completado: el guard #420 sincroniza status → 'done'.
        $item->update(['estado_aprobacion' => 'completado']);
        $this->assertSame('done', $item->fresh()->status);

        // Lo re-escala (timeout / conflicto de merge / reclamo liberado) SIN tocar `status`.
        $item->update(['estado_aprobacion' => 'aprobado_irving']);

        $fresco = $item->fresh();
        $this->assertSame('pending', $fresco->status,
            'El guard inverso debe devolver status a pending al salir de completado hacia un estado vivo.');
        $this->assertSame('aprobado_irving', $fresco->estado_aprobacion);

        $this->assertTrue(
            RoadmapItem::despachable()->pluck('id')->contains($item->id),
            'El item re-escalado debe volver a ser despachable: scopeDespachable() es quien gobierna el despacho real.'
        );
    }

    public function test_no_toca_un_item_archivado_ni_uno_con_merge_commit(): void
    {
        $archivado = RoadmapItem::create([
            'title'             => 'Item archivado con status done '.uniqid(),
            'estado_aprobacion' => 'aprobado_irving',
            'status'            => 'done',
            'archivado_at'      => now(),
        ]);
        // Un save adicional no debe mover su status: sigue archivado.
        $archivado->touch();
        $this->assertSame('done', $archivado->fresh()->status);

        $conMerge = RoadmapItem::create([
            'title'             => 'Item con merge_commit y status done '.uniqid(),
            'estado_aprobacion' => 'aprobado_irving',
            'status'            => 'done',
            'merge_commit'      => 'abc1234',
        ]);
        $conMerge->touch();
        $this->assertSame('done', $conMerge->fresh()->status);
    }
}
