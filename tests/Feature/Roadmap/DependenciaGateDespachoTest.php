<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/**
 * Fase 2c (#9990277, sub-item de #9990261) — test de REGRESIÓN de integración sobre el gate de
 * dependencias ya cableado en `RoadmapItem::scopeDespachable()` (#9990274): una cadena lineal de
 * sub-items encadenados por `subtasks.descomposicion.depende_de` nunca se despacha fuera de orden.
 *
 * Usa el scope DIRECTO (`RoadmapItem::despachable()`), no `ejecutablesParalelo()`: el pre-filtro de
 * footprint/ronda es una regla de la RONDA, ajena al gate de dependencia que aquí se prueba.
 *
 * USA `DatabaseTransactions` + `CreatesApplication` (rollback al terminar cada test), mismo patrón
 * que `SubItemCommandDependeDeTest` — evita el `migrate:fresh --seed` de `Tests\TestCase` en cada
 * test y no ensucia la BD compartida de dev.
 */
class DependenciaGateDespachoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    /**
     * `estado_aprobacion = 'aprobado_irving'` pasa TODAS las cláusulas de `scopeDespachable()`
     * (el gate de estado Y el gate de nivel) sin importar `nivel_riesgo`, la política base de la
     * Torre ni si el revisor está prendido — así el único freno bajo prueba es el de dependencia.
     */
    private function crearItem(?int $padreId, int $position, array $dependeDe, string $titulo): RoadmapItem
    {
        return RoadmapItem::create([
            'title'             => $titulo.' '.uniqid(),
            'estado_aprobacion' => 'aprobado_irving',
            'status'            => 'pending',
            'origen_item_id'    => $padreId,
            'position'          => $position,
            'subtasks'          => ['descomposicion' => ['depende_de' => $dependeDe]],
        ]);
    }

    public function test_cadena_lineal_nunca_despacha_fuera_de_orden(): void
    {
        $padre = RoadmapItem::create([
            'title'             => 'Padre de prueba (cadena depende_de) '.uniqid(),
            'estado_aprobacion' => 'aprobado_revisor',
        ]);

        $sub1 = $this->crearItem($padre->id, 1, [], 'Sub-item 1 (raíz)');
        $sub2 = $this->crearItem($padre->id, 2, [1], 'Sub-item 2 (depende de 1)');
        $sub3 = $this->crearItem($padre->id, 3, [2], 'Sub-item 3 (depende de 2)');

        // Estado inicial: nada completado. Solo el 1 (sin dependencias) despacha.
        $despachables = RoadmapItem::despachable()->pluck('id');
        $this->assertTrue($despachables->contains($sub1->id), 'El sub-item raíz (sin dependencias) debe ser despachable.');
        $this->assertFalse($despachables->contains($sub2->id), 'El sub-item 2 NO debe despachar: su predecesor (1) no está completado.');
        $this->assertFalse($despachables->contains($sub3->id), 'El sub-item 3 NO debe despachar: ni siquiera su predecesor directo (2) existe como completado.');

        // Se completa el 1: el 2 se libera, el 3 sigue bloqueado (depende del 2, aún no completado).
        $sub1->update(['estado_aprobacion' => 'completado']);

        $despachables = RoadmapItem::despachable()->pluck('id');
        $this->assertTrue($despachables->contains($sub2->id), 'El sub-item 2 debe despachar en cuanto su predecesor (1) está completado.');
        $this->assertFalse($despachables->contains($sub3->id), 'El sub-item 3 sigue bloqueado: su predecesor directo (2) todavía no está completado.');

        // Se completa el 2: el 3 se libera.
        $sub2->update(['estado_aprobacion' => 'completado']);

        $despachables = RoadmapItem::despachable()->pluck('id');
        $this->assertTrue($despachables->contains($sub3->id), 'El sub-item 3 debe despachar en cuanto su predecesor (2) está completado.');
    }
}
