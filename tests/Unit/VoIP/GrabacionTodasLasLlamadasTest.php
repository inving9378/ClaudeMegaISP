<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Models\GrupoTimbrado;
use App\Modules\Addons\VoIP\Models\Troncal;
use App\Modules\Addons\VoIP\Services\DialplanGeneratorService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * 24-nov: todas las llamadas se graban, no solo las de la cola. Revisa el plan de marcado
 * que genera MegaISP y la plantilla de extensions.conf. Sin BD.
 */
class GrabacionTodasLasLlamadasTest extends TestCase
{
    private const MIXMONITOR = 'MixMonitor(${GRABACIONES_DIR}/${UNIQUEID}.wav)';
    private const CDR        = 'Set(CDR(grabacion)=${UNIQUEID}.wav)';
    private const LIMPIA     = 'ExecIf($["${DIALSTATUS}" != "ANSWER"]?Set(CDR(grabacion)=))';

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

    private function privado(string $metodo, ...$args): string
    {
        $m = new \ReflectionMethod(DialplanGeneratorService::class, $metodo);
        $m->setAccessible(true);

        return $m->invoke(new DialplanGeneratorService(), ...$args);
    }

    /** Cuenta las grabaciones que arranca cada extensión (bloque exten = …) del texto. */
    private function grabacionesPorExtension(string $plan): array
    {
        $por = [];
        $actual = null;
        foreach (explode("\n", $plan) as $linea) {
            if (preg_match('/^exten = ([^,]+),1,/', $linea, $m)) {
                $actual = $m[1];
                $por[$actual] = 0;
            }
            if ($actual !== null && str_contains($linea, self::MIXMONITOR)) {
                $por[$actual]++;
            }
        }

        return $por;
    }

    public function test_la_grabacion_no_se_repite_si_la_llamada_pasa_por_dos_puntos(): void
    {
        $lineas = implode("\n", DialplanGeneratorService::lineasGrabar());

        $this->assertStringContainsString('ExecIf($["${GRABANDO}" = ""]?' . self::CDR . ')', $lineas);
        $this->assertStringContainsString('ExecIf($["${GRABANDO}" = ""]?' . self::MIXMONITOR . ')', $lineas);
        $this->assertStringContainsString('Set(GRABANDO=1)', $lineas);
        $this->assertStringNotContainsString('Gosub', $lineas, 'sin subrutina compartida: una que falte cuelga la llamada');
    }

    public function test_salientes_del_personal_se_graban_antes_de_marcar(): void
    {
        $t = new Troncal(['nombre' => 'Prueba', 'host' => 'sip.ejemplo.mx', 'puerto' => 5060]);
        $t->id = 2;
        $ctx = (new DialplanGeneratorService())->contextoSalida($t);

        $this->assertLessThan(strpos($ctx, 'Dial('), strpos($ctx, self::MIXMONITOR));
        $this->assertGreaterThan(strpos($ctx, 'Dial('), strpos($ctx, self::LIMPIA), 'sin contestar: la fila queda sin grabación');
    }

    public function test_entrantes_por_troncal_y_grupo_normal_se_graban(): void
    {
        $grupo = new GrupoTimbrado(['nombre' => 'Atención', 'estrategia' => 'ringall', 'es_cola' => false, 'ring_time' => 20, 'destino_fallback' => 'colgar']);
        $grupo->id = 1;
        $grupo->setRelation('extensiones', collect([new Extension(['numero' => '1001']), new Extension(['numero' => '2001'])]));
        $troncal = new Troncal(['nombre' => 'Servnet']);
        $troncal->id = 4;
        $troncal->setRelation('grupoEntrante', $grupo);

        $entrada = $this->privado('buildInboundTrunkContext', $troncal);
        $this->assertSame(['s' => 1, '_X.' => 1, 'i' => 0, 't' => 0], $this->grabacionesPorExtension($entrada));
        $this->assertLessThan(strpos($entrada, self::MIXMONITOR), strpos($entrada, 'Answer()'));

        $plan = $this->privado('buildGrupoContext', $grupo);
        $this->assertSame(1, $this->grabacionesPorExtension($plan)['s'], 'antes solo grababa la cola');
        $this->assertLessThan(strpos($plan, 'Dial('), strpos($plan, self::MIXMONITOR));
    }

    public function test_campanas_graban_lo_que_entra_y_lo_que_sale(): void
    {
        $plan = (new DialplanGeneratorService())->planCampanas('from-servnet', 'cobranza-blaster', 3, 'Atención');

        [$entrada, $blaster] = explode('[cobranza-blaster]', $plan);
        $this->assertSame(['s' => 1, '_X.' => 1, 'i' => 0, 't' => 0], $this->grabacionesPorExtension($entrada));
        $this->assertSame(1, $this->grabacionesPorExtension($blaster)['s']);
        $this->assertLessThan(strpos($blaster, 'Background('), strpos($blaster, self::MIXMONITOR), 'se graba el mensaje que oye el cliente');
    }

    public function test_internas_se_graban_en_la_plantilla(): void
    {
        $tpl = file_get_contents(dirname(__DIR__, 3) . '/resources/asterisk/plantillas/extensions.conf.tpl');
        preg_match('/\[from-internal\](.*?); ─── Servicios del sistema/s', $tpl, $m);
        $internas = $m[1] ?? '';

        $this->assertSame(['_[1-9]XXX' => 1, '_[1-9]XX' => 1], $this->grabacionesPorExtension($internas));
        $this->assertSame(2, substr_count($internas, self::LIMPIA));
        $this->assertStringContainsString('GRABACIONES_DIR={{GRABACIONES_DIR}}', $tpl);
        $this->assertStringNotContainsString('MixMonitor', explode('; ─── Servicios del sistema', $tpl)[1], 'eco y «quién soy» no se graban');
    }
}
