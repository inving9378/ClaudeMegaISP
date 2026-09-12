<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO — un paraguas redirigido por límite de profundidad no debía parecer "sin sub-items
 * abiertos" para siempre.
 *
 * Caso real (#9990902): al intentar `circuito:sub-item` bajo un item ya en la profundidad
 * máxima (`max_profundidad_creacion`, ver `RoadmapIntakeService::crear()`), el ejecutor cuelga
 * el trabajo de un ancestro más arriba y deja constancia en el log (`evento:
 * paraguas_redirigido`, `sub_items_relacionados`). Esos sub-items nacen con `origen_item_id`
 * DISTINTO al del item redirigido — `subItemsAbiertos()` (que antes solo miraba
 * `origen_item_id = $this->id`) nunca los veía, así que el carril `jarvis-ya-decidido`
 * (`JarvisService::evaluarYaDecidido()`, guard #757) lo sacaba del parqueo manual una y otra vez
 * pese a que sus hijos reales seguían abiertos — bucle verificado en vivo.
 *
 * `subItemsRedirigidos()` es el núcleo puro del fix (solo lee `$this->log`, sin BD) — se prueba
 * aquí sin bootear Laravel, mismo patrón que `EvaluarYaDecididoEscalarTest`.
 */
class SubItemsRedirigidosTest extends TestCase
{
    public function test_sin_log_no_hay_redirigidos(): void
    {
        $item = new RoadmapItem(['log' => []]);

        $this->assertSame([], $item->subItemsRedirigidos());
    }

    public function test_log_sin_evento_paraguas_redirigido_no_afecta(): void
    {
        $item = new RoadmapItem(['log' => [
            ['ts' => now_stub(), 'evento' => 'item_creado'],
            ['ts' => now_stub(), 'por' => 'revisor:backlog', 'decision' => 'autoriza'],
        ]]);

        $this->assertSame([], $item->subItemsRedirigidos());
    }

    /** Caso real #9990902: redirigido a #9991057 y #9991058. */
    public function test_detecta_los_ids_del_evento_paraguas_redirigido(): void
    {
        $item = new RoadmapItem(['log' => [
            ['ts' => now_stub(), 'evento' => 'item_creado'],
            [
                'ts' => now_stub(),
                'por' => 'wt-1',
                'evento' => 'paraguas_redirigido',
                'motivo' => 'circuito:cabida devolvio NO CABE...',
                'sub_items_relacionados' => [9991057, 9991058],
            ],
        ]]);

        $this->assertSame([9991057, 9991058], $item->subItemsRedirigidos());
    }

    /** Varios eventos de redirección (caso #9991058 -> #9991065) se acumulan sin duplicar. */
    public function test_acumula_varios_eventos_sin_duplicar(): void
    {
        $item = new RoadmapItem(['log' => [
            ['evento' => 'paraguas_redirigido', 'sub_items_relacionados' => [111, 222]],
            ['evento' => 'paraguas_redirigido', 'sub_items_relacionados' => [222, 333]],
        ]]);

        $this->assertSame([111, 222, 333], $item->subItemsRedirigidos());
    }

    /** Datos corruptos (ids no numéricos, entrada sin `sub_items_relacionados`) no revientan. */
    public function test_datos_corruptos_no_revientan(): void
    {
        $item = new RoadmapItem(['log' => [
            ['evento' => 'paraguas_redirigido'],
            ['evento' => 'paraguas_redirigido', 'sub_items_relacionados' => ['abc', 0, null, '42']],
        ]]);

        $this->assertSame([42], $item->subItemsRedirigidos());
    }
}

if (! function_exists(__NAMESPACE__ . '\\now_stub')) {
    function now_stub(): string
    {
        return '2026-09-12T00:00:00-06:00';
    }
}
