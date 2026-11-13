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

        $this->assertSame(1, substr_count($ctx, 'Dial('), 'una sola regla marca; las demás solo recortan la lada');
        $this->assertStringNotContainsString('_00', $ctx);
        $this->assertStringNotContainsString('X.', $ctx);
    }

    /**
     * Simula cómo Asterisk empata cada forma de escribir un número de México: debe acabar
     * en los mismos 10 dígitos de la única regla que marca. Un internacional no empata nada.
     */
    public function test_lada_de_pais_y_prefijos_viejos_acaban_en_10_digitos(): void
    {
        $ctx = (new DialplanGeneratorService())->contextoSalida($this->troncal());
        preg_match_all('/^exten = (_[^,]+),1,(?:Goto\(\$\{EXTEN:(\d+)\},1\)|NoOp)/m', $ctx, $m, PREG_SET_ORDER);
        $reglas = [];
        foreach ($m as $r) {
            $re = '/^' . strtr(preg_quote(substr($r[1], 1), '/'), ['X' => '\\d', '\\[' => '[', '\\]' => ']', '\\-' => '-']) . '$/';
            $reglas[] = [$re, isset($r[2]) && $r[2] !== '' ? (int) $r[2] : null];
        }
        $marcar = function (string $n) use ($reglas, &$marcar): ?string {
            $hits = array_values(array_filter($reglas, fn ($r) => preg_match($r[0], $n)));
            if (count($hits) !== 1) {
                return null;
            }

            return $hits[0][1] === null ? $n : $marcar(substr($n, $hits[0][1]));
        };

        foreach (['5500001234', '525500001234', '+525500001234', '5215500001234', '+5215500001234',
                  '0445500001234', '0455500001234', '015500001234'] as $escrito) {
            $this->assertSame('5500001234', $marcar($escrito), "«{$escrito}» debe marcar 5500001234");
        }
        foreach (['13055551234', '+13055551234', '0013055551234', '550000123', '1003'] as $escrito) {
            $this->assertNull($marcar($escrito), "«{$escrito}» no debe salir a la calle");
        }
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
