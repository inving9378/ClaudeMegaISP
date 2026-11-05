<?php

namespace Tests\Feature\Roadmap;

use App\Models\User;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\SeguimientoItemService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Gate;
use Tests\CreatesApplication;

/**
 * Botón «Seguimiento» del árbol de la Torre (2026-10-26, pedido de Irving): explica por qué un item
 * no avanza y lo mete al circuito cuando lo que falta es una decisión o un freno. También fija que
 * cada módulo del árbol muestre los items que cuelgan de un proyecto de otro módulo.
 *
 * `DatabaseTransactions` + `CreatesApplication` (rollback, sin migrate:fresh), igual que los demás
 * candados de Roadmap.
 */
class SeguimientoItemArbolTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function item(array $attrs = []): RoadmapItem
    {
        return RoadmapItem::create($attrs + [
            'title'             => 'UNI-PRUEBA seguimiento ' . uniqid(),
            'status'            => 'pending',
            'estado_aprobacion' => 'aprobado_irving',
            'nivel_riesgo'      => 'B',
            'modulo'            => 'UNI-PRUEBA',
        ]);
    }

    private function seguimiento(): SeguimientoItemService
    {
        return app(SeguimientoItemService::class);
    }

    public function test_aprobado_fuera_de_la_cola_se_mete_y_sale_sin_frenos(): void
    {
        $item = $this->item(['excluir_pool_automatico' => true, 'motivo_bloqueo' => 'parqueado a mano']);

        $diag = $this->seguimiento()->diagnostico($item);
        $this->assertSame('frenado', $diag['situacion']);
        $this->assertTrue($diag['puede_meter']);

        $r = $this->seguimiento()->meterAlCircuito($item, [], 'irving:prueba');

        $item->refresh();
        $this->assertFalse((bool) $item->excluir_pool_automatico);
        $this->assertTrue((bool) $item->urgente);
        $this->assertSame('alta', $item->priority);
        $this->assertSame('aprobado_irving', $item->estado_aprobacion);
        $this->assertSame('irving:prueba', $item->aprobado_por);
        $evento = collect($item->log)->firstWhere('evento', 'metido_al_circuito');
        $this->assertNotNull($evento);
        $this->assertTrue($evento['freno_quitado']['excluir_pool_automatico']);
        $this->assertTrue($r['ok'], $r['mensaje']);
    }

    public function test_lo_que_espera_merge_paraguas_o_dependencia_no_se_mete(): void
    {
        $merge = $this->item(['esperando_merge_irving' => true, 'excluir_pool_automatico' => true, 'branch' => 'circuito/item-x']);
        $padre = $this->item();
        $this->item(['origen_item_id' => $padre->id]);
        $previo = $this->item(['estado_aprobacion' => 'requiere_irving']);
        $dependiente = $this->item(['depende_de' => [$previo->id]]);

        $casos = [
            [$merge, 'espera_merge', 'tu'],
            [$padre, 'paraguas', 'otro_item'],
            [$dependiente, 'dependencia', 'otro_item'],
        ];
        foreach ($casos as [$item, $situacion, $quien]) {
            $diag = $this->seguimiento()->diagnostico($item);
            $this->assertSame($situacion, $diag['situacion'], "#{$item->id}");
            $this->assertSame($quien, $diag['quien']);
            $this->assertFalse($diag['puede_meter']);

            $r = $this->seguimiento()->meterAlCircuito($item, [], 'irving:prueba');
            $this->assertFalse($r['ok']);
            $this->assertSame($situacion, $r['code']);
        }

        $this->assertSame([$previo->id], array_column($this->seguimiento()->diagnostico($dependiente)['dependencias'], 'id'));
        $this->assertTrue((bool) $merge->fresh()->esperando_merge_irving, 'Meter no debe tocar lo que espera merge.');
    }

    public function test_cada_situacion_trae_la_accion_que_la_mueve(): void
    {
        $merge = $this->item(['esperando_merge_irving' => true, 'excluir_pool_automatico' => true, 'branch' => 'circuito/item-x']);
        $bloqueante = $this->item(['estado_aprobacion' => 'requiere_irving']);
        $dependiente = $this->item(['depende_de' => [$bloqueante->id, $merge->id]]);
        $padre = $this->item();
        $hijo = $this->item(['origen_item_id' => $padre->id, 'excluir_pool_automatico' => true]);

        $this->assertSame(['mergear'], array_column($this->seguimiento()->diagnostico($merge)['acciones'], 'tipo'));

        $dep = $this->seguimiento()->diagnostico($dependiente);
        $this->assertSame(['forzar'], array_column($dep['acciones'], 'tipo'), 'Lo que espera a otro item ofrece «Iniciar sin esperar».');
        $filas = collect($dep['dependencias'])->keyBy('id');
        $this->assertSame('meter', $filas[$bloqueante->id]['accion']['tipo'], 'El item que lo frena trae su propio botón.');
        $this->assertSame('mergear', $filas[$merge->id]['accion']['tipo']);

        $par = $this->seguimiento()->diagnostico($padre);
        $this->assertSame($hijo->id, $par['hijos_abiertos'][0]['id']);
        $this->assertSame('meter', $par['hijos_abiertos'][0]['accion']['tipo']);
    }

    public function test_ya_mezclado_con_sub_items_no_ofrece_mergear(): void
    {
        $padre = $this->item(['esperando_merge_irving' => true, 'excluir_pool_automatico' => true,
            'branch' => 'circuito/item-x', 'merge_commit' => str_repeat('a', 40)]);
        $this->item(['origen_item_id' => $padre->id]);

        $diag = $this->seguimiento()->diagnostico($padre);
        $this->assertSame('paraguas', $diag['situacion']);
        $this->assertNotContains('mergear', array_column($diag['acciones'], 'tipo'));
    }

    public function test_iniciar_sin_esperar_quita_la_dependencia_y_lo_mete(): void
    {
        $previo = $this->item(['estado_aprobacion' => 'requiere_irving']);
        $dependiente = $this->item(['depende_de' => [$previo->id], 'excluir_pool_automatico' => true]);

        $r = $this->seguimiento()->meterAlCircuito($dependiente, [], 'irving:prueba', true);

        $this->assertTrue($r['ok'], $r['mensaje']);
        $dependiente->refresh();
        $this->assertSame([], $dependiente->depende_de);
        $this->assertFalse((bool) $dependiente->excluir_pool_automatico);
        $evento = collect($dependiente->log)->firstWhere('evento', 'metido_al_circuito');
        $this->assertSame([$previo->id], $evento['dependencias_quitadas']['depende_de'], 'Lo que se quitó queda en el log.');
    }

    public function test_decision_exige_opcion_y_registra_la_elegida(): void
    {
        $item = $this->item([
            'estado_aprobacion' => 'requiere_irving',
            'nivel_riesgo'      => 'C',
            'preguntas'         => [[
                'id'       => 'q1',
                'pregunta' => '¿Qué hacemos?',
                'opciones' => [
                    ['texto' => 'Opción 1: esperar', 'recomendada' => false],
                    ['texto' => 'Opción 2: hacerlo ya', 'recomendada' => true],
                ],
            ]],
        ]);

        $diag = $this->seguimiento()->diagnostico($item);
        $this->assertSame('decision', $diag['situacion']);
        $this->assertSame(1, $diag['preguntas_pendientes']);
        $this->assertTrue($diag['preguntas'][0]['opciones'][1]['recomendada']);

        $sin = $this->seguimiento()->meterAlCircuito($item, [], 'irving:prueba');
        $this->assertFalse($sin['ok']);
        $this->assertSame('preguntas_pendientes', $sin['code']);

        $clave = $diag['preguntas'][0]['opciones'][1]['clave'];
        $this->seguimiento()->meterAlCircuito($item->fresh(), ['q1' => $clave], 'irving:prueba');

        $item->refresh();
        $this->assertSame('aprobado_irving', $item->estado_aprobacion);
        $this->assertSame($clave, $item->preguntasNormalizadas()[0]['opcion_elegida']);
    }

    public function test_cada_modulo_muestra_los_items_que_cuelgan_de_otro_modulo(): void
    {
        Gate::before(fn () => true);
        $this->actingAs(User::query()->firstOrFail());

        $modA = 'UNI-PRUEBA-A-' . uniqid();
        $modB = 'UNI-PRUEBA-B-' . uniqid();
        $proyecto = $this->item(['modulo' => $modA]);
        $propio = $this->item(['modulo' => $modA, 'origen_item_id' => $proyecto->id]);
        $ajeno = $this->item(['modulo' => $modB, 'origen_item_id' => $proyecto->id]);

        $modulos = collect($this->getJson('/api/roadmap/torre/panorama-jerarquia?nivel=modulo')->assertOk()->json('nodos'))
            ->keyBy('id');
        $this->assertArrayHasKey($modB, $modulos->all(), 'Un módulo que solo tiene sub-items de otro proyecto también aparece.');
        $this->assertSame(2, array_sum($modulos[$modA]['contador_hijos']), 'El módulo cuenta todos sus items vivos.');

        $bajoB = collect($this->getJson('/api/roadmap/torre/panorama-jerarquia?nivel=epica&parent_id=' . urlencode($modB))
            ->assertOk()->json('nodos'));
        $this->assertSame([$ajeno->id], $bajoB->pluck('id')->all());
        $this->assertSame($proyecto->id, $bajoB->first()['origen_id']);

        $bajoA = collect($this->getJson('/api/roadmap/torre/panorama-jerarquia?nivel=epica&parent_id=' . urlencode($modA))
            ->assertOk()->json('nodos'));
        $this->assertSame([$proyecto->id], $bajoA->pluck('id')->all(), 'El sub-item del mismo módulo sigue anidado en su proyecto.');

        $hijos = collect($this->getJson('/api/roadmap/torre/panorama-jerarquia?nivel=item&parent_id=' . $proyecto->id)
            ->assertOk()->json('nodos'));
        $this->assertEqualsCanonicalizing([$propio->id, $ajeno->id], $hijos->pluck('id')->all());
        $this->assertSame($modB, $hijos->firstWhere('id', $ajeno->id)['modulo']);
    }
}
