<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Tests\CreatesApplication;

/**
 * Fase 1c-i-a (#9990280, sub-item de #9990269) — tests de integración de `circuito:sub-item`
 * cubriendo `--depende-de` (#9990266, ya mergeado en main): camino feliz, posición inexistente
 * entre hermanos, y regresión SIN `--depende-de`. El 4o escenario del spec original de #9990269
 * (ciclo) queda fuera de este archivo — ya lo cubre `DependenciaGateTest` (el gate en sí) y la
 * detección de #9990278 mergeada; un test de integración de ciclo dedicado es un sub-item aparte.
 *
 * USA `DatabaseTransactions` (rollback al terminar cada test) + `CreatesApplication` en vez de
 * `Tests\TestCase`: ese `setUp()` corre `migrate:fresh --seed` en CADA test, carísimo e
 * innecesario aquí (la tabla `roadmap_items` ya existe en la base de pruebas). Mismo patrón que
 * `tests/Feature/Core/Security/PermisosResolucionTest.php`.
 */
class SubItemCommandDependeDeTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function crearPadre(): RoadmapItem
    {
        return RoadmapItem::create([
            'title'             => 'Padre de prueba '.uniqid(),
            'estado_aprobacion' => 'aprobado_revisor',
        ]);
    }

    private function crearSubItem(RoadmapItem $padre, string $titulo, ?string $dependeDe = null): int
    {
        $params = [
            'padre'    => $padre->id,
            '--sid'    => 'wt-test',
            '--titulo' => $titulo,
            '--spec'   => 'Spec de prueba',
        ];

        if ($dependeDe !== null) {
            $params['--depende-de'] = $dependeDe;
        }

        return Artisan::call('circuito:sub-item', $params);
    }

    /**
     * (a) Camino feliz: 2 sub-items, el segundo depende de la posición del primero. La posición
     * del segundo es max+1 y su `subtasks.descomposicion.depende_de` refleja la posición dada.
     */
    public function test_camino_feliz_segundo_sub_item_depende_del_primero(): void
    {
        $padre = $this->crearPadre();

        $this->assertSame(0, $this->crearSubItem($padre, 'Sub-item 1'));

        $sub1 = RoadmapItem::where('origen_item_id', $padre->id)->orderBy('id')->first();
        $this->assertNotNull($sub1);
        $this->assertSame(1, $sub1->position);
        $this->assertSame([], $sub1->subtasks['descomposicion']['depende_de']);

        $this->assertSame(
            0,
            $this->crearSubItem($padre, 'Sub-item 2', (string) $sub1->position)
        );

        $sub2 = RoadmapItem::where('origen_item_id', $padre->id)
            ->where('id', '!=', $sub1->id)
            ->orderBy('id')
            ->first();

        $this->assertNotNull($sub2);
        $this->assertSame($sub1->position + 1, $sub2->position);
        $this->assertSame([$sub1->position], $sub2->subtasks['descomposicion']['depende_de']);
        $this->assertSame('pendiente_revision', $sub2->estado_aprobacion);

        $this->assertSame(2, RoadmapItem::where('origen_item_id', $padre->id)->count());
    }

    /**
     * (b) `--depende-de` apuntando a una posición que NO existe entre los hermanos: el comando
     * falla (exit != 0) y NO crea ningún item nuevo.
     */
    public function test_depende_de_con_posicion_inexistente_no_crea_nada(): void
    {
        $padre = $this->crearPadre();

        $this->assertSame(0, $this->crearSubItem($padre, 'Sub-item 1'));

        $antesTotal = RoadmapItem::count();
        $antesHijos = RoadmapItem::where('origen_item_id', $padre->id)->count();

        $exit = $this->crearSubItem($padre, 'Sub-item con dependencia inexistente', '999');

        $this->assertNotSame(0, $exit);
        $this->assertSame($antesTotal, RoadmapItem::count(), 'No debe haberse creado ningún item nuevo.');
        $this->assertSame(
            $antesHijos,
            RoadmapItem::where('origen_item_id', $padre->id)->count(),
            'No debe haberse creado ningún sub-item nuevo bajo el padre.'
        );
    }

    /**
     * (c) Regresión: camino feliz SIN `--depende-de` sigue funcionando igual que antes de #9990266
     * — position calculada correctamente, `depende_de` vacío.
     */
    public function test_camino_feliz_sin_depende_de_sigue_funcionando(): void
    {
        $padre = $this->crearPadre();

        $this->assertSame(0, $this->crearSubItem($padre, 'Sub-item sin dependencias'));

        $sub = RoadmapItem::where('origen_item_id', $padre->id)->first();
        $this->assertNotNull($sub);
        $this->assertSame(1, $sub->position);
        $this->assertSame([], $sub->subtasks['descomposicion']['depende_de']);
        $this->assertSame('pendiente_revision', $sub->estado_aprobacion);
        $this->assertSame($padre->id, $sub->origen_item_id);

        // Un segundo sub-item sin --depende-de sigue avanzando la posición normalmente.
        $this->assertSame(0, $this->crearSubItem($padre, 'Sub-item sin dependencias 2'));
        $sub2 = RoadmapItem::where('origen_item_id', $padre->id)
            ->where('id', '!=', $sub->id)
            ->first();
        $this->assertNotNull($sub2);
        $this->assertSame(2, $sub2->position);
        $this->assertSame([], $sub2->subtasks['descomposicion']['depende_de']);
    }
}
