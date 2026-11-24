<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\VoIP\Services\DialplanGeneratorService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/** Plan de marcado de la troncal propia de Campañas/Blaster (entrantes y salientes). Sin BD. */
class TroncalCampanasTest extends TestCase
{
    protected function setUp(): void
    {
        $app = new Container();
        Container::setInstance($app);
        $app->instance('config', new Repository([
            'requisitos-voip' => ['asterisk' => ['generados_dir' => '/tmp']],
            'voip'            => ['trunk_endpoint_id' => 'servnet'],
        ]));
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function test_lo_que_entra_por_la_troncal_timbra_en_el_grupo(): void
    {
        $plan = (new DialplanGeneratorService())->planCampanas('from-servnet', 'cobranza-blaster', 3, 'Atencion a Clientes');

        $this->assertStringContainsString("[from-servnet]\nexten = s,1,", $plan);
        $this->assertSame(2, substr_count($plan, 'Goto(grupo-3,s,1)'), 'sin DID (s) y con DID (_X.)');
        $this->assertStringContainsString('exten = _X.,1,', $plan);
    }

    public function test_sin_grupo_activo_cuelga_en_vez_de_perderse(): void
    {
        $plan = (new DialplanGeneratorService())->planCampanas('from-servnet', 'cobranza-blaster', null, null);

        $this->assertStringContainsString('[from-servnet]', $plan);
        $this->assertStringNotContainsString('grupo-', $plan);
        $this->assertStringContainsString('exten = _X.,1,Goto(s,1)', $plan);
    }

    public function test_lo_que_sale_reproduce_el_mensaje_de_la_campana(): void
    {
        $plan = (new DialplanGeneratorService())->planCampanas('from-servnet', 'cobranza-blaster', 3, 'Atencion a Clientes');

        $this->assertStringContainsString("[cobranza-blaster]\nexten = s,1,", $plan);
        $this->assertStringContainsString('Background(${AUDIO_BASE})', $plan);
        $this->assertStringContainsString('PJSIP/servnet/', $plan);
        $this->assertStringNotContainsString('Dial(', $plan, 'el blaster no marca a otra troncal desde el plan');
    }
}
