<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.
use ReflectionMethod;

/**
 * CANDADO DEL FIX #9990562 — el tope de dígitos de los regex de #item reintroducía el bug
 * de #210/#640. `parseCurrentItem()`/`parseFases()` usaban `#(\d{1,6})`, pero los ids del
 * roadmap ya son de 7 cifras (9990000+) → NUNCA matcheaban, `current_item` resolvía siempre
 * null y `renovarLease()` caía a su fallback (renovaba TODOS los `en_progreso` del
 * worker_sid, no solo el que la vuelta trabajaba de verdad). `RenovarLeaseFiltraPorItemTest`
 * blinda que `renovarLease()` acote por `id` CUANDO recibe un currentItemId — este test
 * blinda la mitad que le faltaba: que el parseo de verdad ENTREGUE ese id con 7+ dígitos.
 */
class ParseCurrentItemSieteDigitosTest extends TestCase
{
    private function invocar(string $metodo, array $args)
    {
        $reflection = new ReflectionMethod(RoadmapCircuitoService::class, $metodo);
        $reflection->setAccessible(true);

        return $reflection->invoke(new RoadmapCircuitoService(), ...$args);
    }

    public function test_parse_current_item_reconoce_id_de_siete_digitos(): void
    {
        $tail = "algo de log\nCIRCUITO_FASE: editando #9990524\notra linea";

        $this->assertSame(9990524, $this->invocar('parseCurrentItem', [$tail]));
    }

    public function test_parse_current_item_toma_el_ultimo_id_mencionado(): void
    {
        $tail = 'trabajando el item #100, ahora #9990524';

        $this->assertSame(9990524, $this->invocar('parseCurrentItem', [$tail]));
    }

    public function test_parse_current_item_sigue_reconociendo_ids_de_seis_digitos_o_menos(): void
    {
        $this->assertSame(123456, $this->invocar('parseCurrentItem', ['CIRCUITO_FASE: rama #123456']));
        $this->assertSame(42, $this->invocar('parseCurrentItem', ['tocando #42']));
    }

    public function test_parse_current_item_sin_referencias_devuelve_null(): void
    {
        $this->assertNull($this->invocar('parseCurrentItem', ['sin ninguna referencia aqui']));
    }

    public function test_parse_fases_captura_item_id_de_siete_digitos(): void
    {
        $fases = $this->invocar('parseFases', [['CIRCUITO_FASE: editando #9990524'], []]);

        $this->assertCount(1, $fases);
        $this->assertSame('editando', $fases[0]['fase']);
        $this->assertSame(9990524, $fases[0]['item_id']);
    }

    public function test_parse_fases_sin_item_id_sigue_funcionando(): void
    {
        $fases = $this->invocar('parseFases', [['CIRCUITO_FASE: rama'], []]);

        $this->assertCount(1, $fases);
        $this->assertNull($fases[0]['item_id']);
    }
}
