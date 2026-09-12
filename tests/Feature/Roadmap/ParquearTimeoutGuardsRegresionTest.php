<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\CreatesApplication;

/**
 * Item #9990880 (Fase 4 de #9990855) — CANDADO de regresión de las 2 guardas nuevas de
 * `ParquearTimeoutCommand` (guarda de estado + guarda de paraguas), corriendo el comando REAL vía
 * `Artisan::call` contra items de prueba reales, y verificando que el camino preexistente
 * (escalar / reanudar) sigue intacto.
 *
 * USA `DatabaseTransactions` + `CreatesApplication` (rollback al terminar cada test), mismo patrón
 * que `CierreHuecoRebotaTest`/`SubItemCommandDependeDeTest` — no ensucia la BD compartida de dev y
 * evita el `migrate:fresh --seed` de `Tests\TestCase`.
 */
class ParquearTimeoutGuardsRegresionTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    /**
     * (a) Item con sub-items abiertos + timeout con 0 commits (sin rama) → NO escala: se parquea
     * como paraguas (aprobado_irving + excluir_pool_automatico=true) y registra
     * `timeout_no_escalado` en el log. La guarda de paraguas va ANTES del cálculo de commits, así
     * que ni siquiera se llega a evaluar la rama.
     */
    public function test_item_con_subitems_abiertos_no_escala_por_timeout(): void
    {
        $padre = RoadmapItem::create([
            'title'             => 'Paraguas con sub-items abiertos '.uniqid(),
            'status'            => 'pending',
            'estado_aprobacion' => 'en_progreso',
            'worker_sid'        => 'wt-9',
        ]);
        $padre->claimed_at = now();
        $padre->save();

        RoadmapItem::create([
            'title'             => 'Sub-item abierto '.uniqid(),
            'status'            => 'pending',
            'estado_aprobacion' => 'aprobado_revisor',
            'origen_item_id'    => $padre->id,
        ]);

        $exit = Artisan::call('circuito:parquear-timeout', ['item' => $padre->id]);

        $this->assertSame(0, $exit);

        $fresco = $padre->fresh();
        $this->assertSame(
            'aprobado_irving',
            $fresco->estado_aprobacion,
            'Un paraguas con sub-items abiertos no debe escalar a requiere_irving por timeout: su cierre depende de la cascada.'
        );
        $this->assertTrue((bool) $fresco->excluir_pool_automatico);
        $this->assertNull($fresco->worker_sid, 'Debe liberar el reclamo al parquear.');
        $this->assertNull($fresco->claimed_at);
        $this->assertSame(
            0,
            (int) $fresco->veces_timeouteo,
            'La guarda de paraguas no debe contar como timeout real: no es que el item haya girado en vacío.'
        );

        // No se usa ->last(): el guard de FASE 2A.4 (trazabilidad de banderas) agrega una SEGUNDA
        // entrada de log en el mismo save cuando cambia `excluir_pool_automatico` (ver
        // RoadmapItem.php ~676), así que el evento a verificar no es necesariamente el último.
        $evento = collect($fresco->log)->firstWhere('evento', 'timeout_no_escalado');
        $this->assertNotNull($evento, 'Falta el registro timeout_no_escalado en el log del item.');
        $this->assertSame(1, $evento['subitems_abiertos'] ?? null);
    }

    /**
     * (b) NO-REGRESIÓN — item sin sub-items, sin rama, sin commits + timeout → SÍ escala igual que
     * antes de #9990855: pasa a `requiere_irving`, suelta el reclamo y cuenta el timeout.
     */
    public function test_item_sin_subitems_sin_rama_sin_commits_sigue_escalando(): void
    {
        $item = RoadmapItem::create([
            'title'             => 'Item sin avance '.uniqid(),
            'status'            => 'pending',
            'estado_aprobacion' => 'en_progreso',
            'worker_sid'        => 'wt-9',
        ]);
        $item->claimed_at = now();
        $item->save();

        $exit = Artisan::call('circuito:parquear-timeout', ['item' => $item->id]);

        $this->assertSame(0, $exit);

        $fresco = $item->fresh();
        $this->assertSame(
            'requiere_irving',
            $fresco->estado_aprobacion,
            'Sin sub-items abiertos y sin avance en la rama, el comportamiento preexistente (escalar) debe seguir intacto.'
        );
        $this->assertNull($fresco->worker_sid);
        $this->assertNull($fresco->claimed_at);
        $this->assertSame(1, (int) $fresco->veces_timeouteo);

        $evento = collect($fresco->log)->firstWhere('evento', 'timeout_escalado');
        $this->assertNotNull($evento, 'Falta el registro timeout_escalado en el log del item.');
    }

    /**
     * (c) NO-REGRESIÓN — item cuya rama de trabajo (`items.branch`) tiene commits reales, pero la
     * vuelta agotó turnos (`--causa=max_turns`) → se re-encola como REANUDABLE (vuelve al estado
     * previo al reclamo, aquí `aprobado_revisor`), incrementa `veces_timeouteo` y
     * `reanudaciones_timeout`, y NO escala a `requiere_irving`. `commitsDeRama()` se mockea (partial
     * mock de `RoadmapCircuitoService`) para no depender de una rama git real: es la única
     * dependencia externa del comando y el resto de la lógica bajo prueba (guardas + reanudación)
     * es puro PHP/BD.
     */
    public function test_item_con_commits_reales_en_su_rama_se_reanuda_sin_escalar(): void
    {
        $branch = 'circuito/item-test-9990880-'.uniqid();

        $item = RoadmapItem::create([
            'title'             => 'Item con avance real '.uniqid(),
            'status'            => 'pending',
            'estado_aprobacion' => 'en_progreso',
            'worker_sid'        => 'wt-9',
            'branch'            => $branch,
        ]);
        $item->claimed_at          = now();
        $item->estado_previo_claim = 'aprobado_revisor';
        $item->save();

        $mockCircuito = Mockery::mock(RoadmapCircuitoService::class)->makePartial();
        $mockCircuito->shouldReceive('commitsDeRama')->with($branch)->andReturn(3);
        $this->app->instance(RoadmapCircuitoService::class, $mockCircuito);

        $exit = Artisan::call('circuito:parquear-timeout', [
            'item'    => $item->id,
            '--causa' => 'max_turns',
        ]);

        $this->assertSame(0, $exit);

        $fresco = $item->fresh();
        $this->assertSame(
            'aprobado_revisor',
            $fresco->estado_aprobacion,
            'Con commits reales en su rama debe reanudarse (mecanismo preexistente), no escalar: las guardas de #9990855 no deben pisarlo.'
        );
        $this->assertNull($fresco->worker_sid, 'Al reanudar libera el slot para que cualquier terminal lo retome.');
        $this->assertNull($fresco->claimed_at);
        $this->assertSame(1, (int) $fresco->veces_timeouteo);
        $this->assertSame(1, (int) $fresco->reanudaciones_timeout);

        $evento = collect($fresco->log)->firstWhere('evento', 'timeout_reanudable');
        $this->assertNotNull($evento, 'Falta el registro timeout_reanudable en el log del item.');
        $this->assertSame('max_turns', $evento['causa'] ?? null);
    }
}
