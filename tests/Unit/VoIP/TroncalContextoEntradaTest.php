<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\VoIP\Models\Troncal;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * El contexto al que Asterisk manda las llamadas que entran por una troncal. Lo usan el
 * alta, la edición, «Provisionar», el interruptor, el plan de marcado y la sincronización:
 * si alguno pone otro, la troncal queda registrada pero las llamadas no llegan a nadie.
 */
class TroncalContextoEntradaTest extends TestCase
{
    protected function setUp(): void
    {
        Container::setInstance(new Container());
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    private function troncal(array $a): Troncal
    {
        $t = new Troncal($a);
        $t->id = 2;

        return $t;
    }

    public function test_con_grupo_va_al_contexto_que_genera_el_plan(): void
    {
        $this->assertSame('inbound-trunk-2', $this->troncal(['grupo_entrante_id' => 1, 'contexto' => 'from-trunk'])->contextoEntrante());
    }

    public function test_sin_grupo_usa_el_configurado(): void
    {
        $this->assertSame('mi-contexto', $this->troncal(['contexto' => 'mi-contexto'])->contextoEntrante());
    }

    public function test_recien_creada_sin_contexto_no_queda_vacio(): void
    {
        $this->assertSame('from-trunk', $this->troncal([])->contextoEntrante());
        $this->assertSame('from-trunk', $this->troncal(['contexto' => '  '])->contextoEntrante());
    }
}
