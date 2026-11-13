<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\VoIP\Services\Voz\VoiceTtsService;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/** La voz de María usa su propia asignación de IA, también cuando la arma el contenedor. */
class VozMariaTest extends TestCase
{
    private function clave(VoiceTtsService $s): string
    {
        $driver = (new \ReflectionProperty($s, 'driver'))->getValue($s);

        return (new \ReflectionProperty($driver, 'claveIa'))->getValue($driver);
    }

    public function test_armada_por_el_contenedor_usa_voip_voz(): void
    {
        $this->assertSame('voip.voz', $this->clave((new Container())->make(VoiceTtsService::class)));
    }

    public function test_armada_a_mano_usa_voip_voz(): void
    {
        $this->assertSame('voip.voz', $this->clave(new VoiceTtsService()));
    }
}
