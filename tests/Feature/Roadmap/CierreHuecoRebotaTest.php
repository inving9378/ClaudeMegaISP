<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/**
 * Item #9990403 — CANDADO de regresión del incidente real #9990366: un item se marcó `completado`
 * SIN rama de trabajo ni merge_commit (no produjo nada) y `JarvisService::verificarCierre()` lo
 * detectó (`cierre_incompleto` en el log) pero no lo frenó (`bloqueado: false`, modo advertencia).
 *
 * `verificarCierre()` ahora distingue "no hizo el trabajo" (bloquea SIEMPRE, sin importar el rollout
 * de `cierre.bloquea`) de "el trabajo era no-código / ya estaba hecho" (investigación, premisa
 * incorrecta, duplicado ya entregado por otro item) — esta segunda categoría exige la justificación
 * explícita `cierre_sin_codigo_motivo`.
 *
 * USA `DatabaseTransactions` + `CreatesApplication` (rollback al terminar), mismo patrón que
 * `StatusResincronizaAlEscalarTest`/`DependenciaGateDespachoTest` — no ensucia la BD compartida.
 */
class CierreHuecoRebotaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_item_de_codigo_sin_rama_ni_justificacion_no_completa_se_rebota(): void
    {
        $item = RoadmapItem::create([
            'title'             => 'Item de código sin rama ni justificación '.uniqid(),
            'estado_aprobacion' => 'aprobado_irving',
            'status'            => 'pending',
            'reporte_coloquial' => 'algo cambió en algún lado',
            'enlace_revision'   => '/releases',
            'worker_sid'        => 'wt-9',
            'claimed_at'        => now(),
        ]);

        $item->update(['estado_aprobacion' => 'completado']);

        $fresco = $item->fresh();
        $this->assertSame(
            'aprobado_irving',
            $fresco->estado_aprobacion,
            'Un cierre hueco (sin rama, sin merge_commit, sin justificación) no debe quedar completado.'
        );
        $this->assertTrue((bool) $fresco->excluir_pool_automatico);
        $this->assertNull($fresco->worker_sid, 'Debe liberar el reclamo al parquear (#898).');
        $this->assertNull($fresco->claimed_at);

        $ultimoEvento = collect($fresco->log)->last();
        $this->assertSame('cierre_incompleto', $ultimoEvento['evento']);
        $this->assertTrue($ultimoEvento['bloqueado']);
        $this->assertNotEmpty($ultimoEvento['bloqueantes'], 'El faltante de rama debe viajar como bloqueante, no como faltante blando.');
    }

    public function test_item_de_investigacion_con_justificacion_cierra_normal(): void
    {
        $item = RoadmapItem::create([
            'title'                     => 'Item de investigación con justificación '.uniqid(),
            'estado_aprobacion'         => 'aprobado_irving',
            'status'                    => 'pending',
            'reporte_coloquial'         => 'se investigó y la premisa era incorrecta, sin cambio de código',
            'enlace_revision'           => '/releases',
            'cierre_sin_codigo_motivo'  => 'premisa incorrecta: la funcionalidad ya existía, ver #123',
        ]);

        $item->update(['estado_aprobacion' => 'completado']);

        $fresco = $item->fresh();
        $this->assertSame('completado', $fresco->estado_aprobacion, 'Un cierre sin código justificado debe completar normal.');
        $this->assertFalse((bool) $fresco->excluir_pool_automatico);
    }

    public function test_item_con_rama_y_sin_merge_commit_todavia_cierra_normal(): void
    {
        // Caso real de un cierre en curso: `circuito:rama` ya corrió (branch presente) pero
        // `merge_commit` aún no aterriza (o el item se completa justo cuando MergeRunner lo setea en
        // la misma operación). El gate de #9990403 solo bloquea cuando AMBOS faltan.
        $item = RoadmapItem::create([
            'title'             => 'Item con rama, sin merge_commit '.uniqid(),
            'estado_aprobacion' => 'aprobado_irving',
            'status'            => 'pending',
            'reporte_coloquial' => 'cambio real hecho en su rama',
            'enlace_revision'   => '/releases',
            'branch'            => 'circuito/item-999-prueba',
        ]);

        $item->update(['estado_aprobacion' => 'completado']);

        $this->assertSame('completado', $item->fresh()->estado_aprobacion);
    }
}
