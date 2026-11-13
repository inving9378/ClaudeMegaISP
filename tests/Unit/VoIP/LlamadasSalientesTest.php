<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\VoIP\Models\Troncal;
use App\Modules\Addons\VoIP\Services\DialplanGeneratorService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/** La regla de llamadas a la calle que se agrega al plan de marcado generado. Sin BD. */
class LlamadasSalientesTest extends TestCase
{
    protected function setUp(): void
    {
        $app = new Container();
        Container::setInstance($app);
        $app->instance('config', new Repository(['requisitos-voip' => ['asterisk' => ['generados_dir' => '/tmp']]]));
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    private function troncal(array $a = []): Troncal
    {
        $t = new Troncal($a + ['nombre' => 'Prueba', 'host' => 'sip.ejemplo.mx', 'puerto' => 5060]);
        $t->id = 2;

        return $t;
    }

    public function test_marca_10_digitos_por_la_troncal_a_la_uri_del_proveedor(): void
    {
        $ctx = (new DialplanGeneratorService())->contextoSalida($this->troncal());

        $this->assertStringContainsString("[from-internal]\nexten = _[2-9]XXXXXXXXX,1,", $ctx);
        $this->assertStringContainsString('Dial(PJSIP/trunk_2/sip:${EXTEN}@sip.ejemplo.mx:5060,60)', $ctx);
        $this->assertStringContainsString('Set(CDR(userfield)=saliente)', $ctx);
    }

    public function test_no_permite_internacionales_ni_otros_largos(): void
    {
        $ctx = (new DialplanGeneratorService())->contextoSalida($this->troncal());

        $this->assertSame(1, preg_match_all('/^exten = /m', $ctx), 'una sola regla de salida');
        $this->assertStringNotContainsString('_00', $ctx);
        $this->assertStringNotContainsString('X.', $ctx);
    }

    public function test_sin_troncal_no_hay_regla(): void
    {
        $ctx = (new DialplanGeneratorService())->contextoSalida(null);

        $this->assertStringNotContainsString('[from-internal]', $ctx);
        $this->assertStringNotContainsString('exten', $ctx);
    }

    public function test_sin_puerto_usa_el_del_proveedor(): void
    {
        $ctx = (new DialplanGeneratorService())->contextoSalida($this->troncal(['puerto' => null]));

        $this->assertStringContainsString('Dial(PJSIP/trunk_2/sip:${EXTEN}@sip.ejemplo.mx,60)', $ctx);
    }
}
