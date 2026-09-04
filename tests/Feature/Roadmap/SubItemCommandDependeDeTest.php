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
 * entre hermanos, y regresión SIN `--depende-de`.
 *
 * Fase 1c-i-b (#9990281, sub-item de #9990269) agrega el 4o escenario del spec original: intentar
 * crear un sub-item que cerraría un CICLO. `DependenciaGateTest` (unit) ya cubre el algoritmo
 * `tieneCiclo()`/`caminoCiclo()` con grafos sintéticos arbitrarios; lo que falta aquí es la
 * integración real contra `circuito:sub-item`. Con el comando real, un ciclo NUNCA surge por uso
 * normal: cada sub-item nuevo recibe `position = max(hermanos) + 1` y solo puede `--depende-de`
 * posiciones YA EXISTENTES (por tanto siempre menores) — los edges resultantes son un DAG por
 * construcción, y no existe ningún comando que reescriba el `depende_de` de un item ya creado.
 * Por eso, para ejercitar la rama de rechazo del comando (#9990278), el escenario fuerza el ciclo
 * EDITANDO directo el modelo de un hermano ya existente (fuera del gate, como si un futuro
 * "editar dependencias" lo hubiera reescrito) y luego intenta crear un sub-item nuevo que
 * enganche a ese componente ya-cíclico — mismo patrón que la verificación manual de #9990279
 * (`docs/circuito-cc-subitem-deteccion-ciclos-item-9990279-verificacion.md`).
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
     * Fuerza (fuera del gate, edición directa del modelo) el `depende_de` de un hermano YA
     * creado — simula lo que haría un futuro "editar dependencias" y es la única forma de armar
     * un ciclo real entre hermanos, dado que `circuito:sub-item` solo permite depender de
     * posiciones ya existentes (nunca hacia adelante).
     */
    private function forzarDependeDe(RoadmapItem $padre, int $position, array $nuevasDependencias): void
    {
        $hermano = RoadmapItem::where('origen_item_id', $padre->id)
            ->where('position', $position)
            ->firstOrFail();

        $hermano->subtasks = ['descomposicion' => ['depende_de' => $nuevasDependencias]];
        $hermano->save();
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

    /**
     * (d) Ciclo DIRECTO entre 2 hermanos ya existentes (1↔2, forzado fuera del gate): un tercer
     * sub-item que se enganche a ese componente ciclado es RECHAZADO (exit != 0), el mensaje trae
     * el camino del ciclo, y NO se crea ninguna fila nueva.
     */
    public function test_depende_de_que_cerraria_ciclo_directo_no_crea_nada(): void
    {
        $padre = $this->crearPadre();

        $this->assertSame(0, $this->crearSubItem($padre, 'Hijo 1'));
        $hijo1 = RoadmapItem::where('origen_item_id', $padre->id)->orderBy('id')->first();

        $this->assertSame(0, $this->crearSubItem($padre, 'Hijo 2', (string) $hijo1->position));
        $hijo2 = RoadmapItem::where('origen_item_id', $padre->id)
            ->where('id', '!=', $hijo1->id)
            ->orderBy('id')
            ->first();

        // Fuerza el ciclo 1<->2: el hijo1 (ya creado sin dependencias) pasa a depender del hijo2.
        $this->forzarDependeDe($padre, $hijo1->position, [$hijo2->position]);

        $antesTotal = RoadmapItem::count();
        $antesHijos = RoadmapItem::where('origen_item_id', $padre->id)->count();

        $exit = $this->crearSubItem($padre, 'Hijo 3 (dispararía el ciclo)', (string) $hijo1->position);
        $salida = Artisan::output(); // fetch() drena el buffer: leerlo UNA vez y reusar.

        $this->assertNotSame(0, $exit);
        $camino = "{$hijo1->position} -> {$hijo2->position} -> {$hijo1->position}";
        $this->assertStringContainsString('dependería circularmente', $salida);
        $this->assertStringContainsString($camino, $salida);
        $this->assertSame($antesTotal, RoadmapItem::count(), 'No debe haberse creado ningún item nuevo.');
        $this->assertSame(
            $antesHijos,
            RoadmapItem::where('origen_item_id', $padre->id)->count(),
            'No debe haberse creado ningún sub-item nuevo bajo el padre.'
        );
    }

    /**
     * (e) Ciclo INDIRECTO entre 3 hermanos ya existentes (A->C->B->A, forzado fuera del gate): un
     * cuarto sub-item que se enganche a ese componente ciclado es RECHAZADO, con el camino
     * completo del ciclo en el mensaje, y sin crear ninguna fila nueva.
     */
    public function test_depende_de_que_cerraria_ciclo_indirecto_no_crea_nada(): void
    {
        $padre = $this->crearPadre();

        $this->assertSame(0, $this->crearSubItem($padre, 'Hijo A'));
        $hijoA = RoadmapItem::where('origen_item_id', $padre->id)->orderBy('id')->first();

        $this->assertSame(0, $this->crearSubItem($padre, 'Hijo B', (string) $hijoA->position));
        $hijoB = RoadmapItem::where('origen_item_id', $padre->id)
            ->where('id', '!=', $hijoA->id)
            ->orderBy('id')
            ->first();

        $this->assertSame(0, $this->crearSubItem($padre, 'Hijo C', (string) $hijoB->position));
        $hijoC = RoadmapItem::where('origen_item_id', $padre->id)
            ->whereNotIn('id', [$hijoA->id, $hijoB->id])
            ->orderBy('id')
            ->first();

        // Fuerza el ciclo A->C->B->A: hijoA (raíz, sin dependencias) pasa a depender de hijoC.
        $this->forzarDependeDe($padre, $hijoA->position, [$hijoC->position]);

        $antesTotal = RoadmapItem::count();
        $antesHijos = RoadmapItem::where('origen_item_id', $padre->id)->count();

        $exit = $this->crearSubItem($padre, 'Hijo D (dispararía el ciclo)', (string) $hijoA->position);
        $salida = Artisan::output(); // fetch() drena el buffer: leerlo UNA vez y reusar.

        $this->assertNotSame(0, $exit);
        $camino = "{$hijoA->position} -> {$hijoC->position} -> {$hijoB->position} -> {$hijoA->position}";
        $this->assertStringContainsString('dependería circularmente', $salida);
        $this->assertStringContainsString($camino, $salida);
        $this->assertSame($antesTotal, RoadmapItem::count(), 'No debe haberse creado ningún item nuevo.');
        $this->assertSame(
            $antesHijos,
            RoadmapItem::where('origen_item_id', $padre->id)->count(),
            'No debe haberse creado ningún sub-item nuevo bajo el padre.'
        );
    }
}
