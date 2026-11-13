<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Services\AsteriskProvisioningService;
use App\Modules\Addons\VoIP\Services\GeneradorConfigAsterisk;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Teléfonos que se conectan desde fuera de la oficina (Zoiper): el transporte aparte en
 * pjsip.conf y lo que lleva cada extensión en ps_endpoints. Sin BD ni Asterisk: solo
 * un contenedor con la configuración que leen estas piezas.
 */
class TelefonosExternosTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/voip-externos-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/etc', 0777, true);
        mkdir($this->tmp . '/soporte', 0777, true);
        $this->config([]);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmp));
        Container::setInstance(null);
    }

    private function config(array $asterisk): void
    {
        $app = new Container();
        Container::setInstance($app);
        $app->instance('config', new Repository([
            'voip' => ['asterisk' => $asterisk],
            'requisitos-voip' => ['asterisk' => ['soporte_dir' => $this->tmp . '/soporte', 'generados_dir' => '/etc/asterisk/megaisp.d']],
        ]));
    }

    private function valoresPjsip(string $bindExterno): array
    {
        return [
            'TRANSPORTE' => 'transport-udp', 'BIND_SIP' => '0.0.0.0', 'EXTERNAL_MEDIA' => '',
            'EXTERNAL_SIGNALING' => '', 'LOCAL_NET' => '', 'BIND_EXTERNO' => $bindExterno,
            'GENERADOS_DIR' => '/etc/asterisk/megaisp.d', 'ARI_PORT' => 8088,
        ];
    }

    private function generador(): GeneradorConfigAsterisk
    {
        return new GeneradorConfigAsterisk(dirname(__DIR__, 3) . '/resources/asterisk/plantillas', $this->tmp . '/etc');
    }

    private function plantilla(): string
    {
        return file_get_contents(dirname(__DIR__, 3) . '/resources/asterisk/plantillas/pjsip.conf.tpl');
    }

    public function test_pjsip_trae_el_transporte_externo_cuando_esta_configurado(): void
    {
        $conf = $this->generador()->rellenar($this->plantilla(), $this->valoresPjsip('203.0.113.10:5080'));

        $this->assertStringContainsString("[transport-externo]\ntype=transport\nprotocol=udp\nbind=203.0.113.10:5080", $conf);
        $this->assertStringContainsString("[transport-udp]\ntype=transport\nprotocol=udp\nbind=0.0.0.0", $conf);
    }

    public function test_pjsip_sin_transporte_externo_queda_como_antes(): void
    {
        $conf = $this->generador()->rellenar($this->plantilla(), $this->valoresPjsip(''));

        $this->assertStringNotContainsString('transport-externo', $conf);
        $this->assertStringNotContainsString('{{', $conf);
        $this->assertStringContainsString('[transport-wss]', $conf);
    }

    public function test_generar_con_solo_escribe_unicamente_ese_archivo(): void
    {
        $origen = dirname(__DIR__, 3) . '/resources/asterisk/plantillas';
        $r = (new GeneradorConfigAsterisk($origen, $this->tmp . '/etc'))
            ->generar($this->valoresPjsip('203.0.113.10:5080'), ['pjsip.conf']);

        $this->assertSame(['pjsip.conf'], $r['escritos']);
        $this->assertSame(['pjsip.conf'], array_values(array_diff(scandir($this->tmp . '/etc'), ['.', '..'])));
        $this->assertStringContainsString('bind=203.0.113.10:5080', file_get_contents($this->tmp . '/etc/pjsip.conf'));
    }

    private function extension(array $a): Extension
    {
        $e = new Extension($a + ['numero' => '1800', 'contexto' => 'from-internal', 'codecs' => 'ulaw,alaw', 'transporte' => 'transport-udp']);
        $e->es_webrtc = $a['es_webrtc'] ?? false;

        return $e;
    }

    public function test_extension_normal_lleva_ajustes_para_routers_y_sin_ip_de_audio(): void
    {
        $this->config(['bind_externo' => '203.0.113.10:5080']);
        $d = (new AsteriskProvisioningService())->datosEndpoint($this->extension([]));

        $this->assertSame('transport-udp', $d['transport']);
        $this->assertSame(['yes', 'yes', 'yes'], [$d['force_rport'], $d['rewrite_contact'], $d['rtp_symmetric']]);
        $this->assertNull($d['media_address']);
        $this->assertNull($d['bind_rtp_to_media_address']);
    }

    public function test_extension_con_transporte_externo_amarra_el_audio_a_la_ip_publica(): void
    {
        $this->config(['bind_externo' => '203.0.113.10:5080']);
        $d = (new AsteriskProvisioningService())->datosEndpoint($this->extension(['transporte' => 'transport-externo']));

        $this->assertSame('transport-externo', $d['transport']);
        $this->assertSame('203.0.113.10', $d['media_address']);
        $this->assertSame('yes', $d['bind_rtp_to_media_address']);
    }

    public function test_transporte_externo_en_0_0_0_0_no_fija_ip_de_audio(): void
    {
        $this->config(['bind_externo' => '0.0.0.0:5080']);
        $d = (new AsteriskProvisioningService())->datosEndpoint($this->extension(['transporte' => 'transport-externo']));

        $this->assertNull($d['media_address']);
        $this->assertNull($d['bind_rtp_to_media_address']);
    }

    public function test_telefono_web_no_cambia(): void
    {
        $this->config(['bind_externo' => '203.0.113.10:5080']);
        $d = (new AsteriskProvisioningService())->datosEndpoint($this->extension(['numero' => 'web1800', 'transporte' => 'transport-wss', 'es_webrtc' => true]));

        $this->assertSame('yes', $d['webrtc']);
        $this->assertArrayNotHasKey('rtp_symmetric', $d);
        $this->assertArrayNotHasKey('rewrite_contact', $d);
        $this->assertNull($d['media_address']);
    }

    public function test_ip_del_transporte_externo(): void
    {
        foreach (['203.0.113.10:5080' => '203.0.113.10', '203.0.113.10' => '203.0.113.10', '' => null,
                  '0.0.0.0:5080' => null, 'basura:5080' => null] as $bind => $esperado) {
            $this->config(['bind_externo' => $bind]);
            $this->assertSame($esperado, AsteriskProvisioningService::ipTransporteExterno(), "bind={$bind}");
        }
    }
}
