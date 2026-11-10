<?php

namespace Tests\Unit\Red;

use App\Services\OltDriver\Huawei\HuaweiTransport;
use App\Services\OltDriver\Huawei\SessionInterface;
use App\Support\Red\ClienteRouterOs;
use App\Support\Red\RedSoloLecturaException;
use App\Support\Red\SoloLectura;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PEAR2\Net\RouterOS\Request;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Red en solo lectura (config/red.php): con la base de producción en dev, ningún cambio debe
 * llegar a un router MikroTik ni a una OLT; las consultas sí.
 */
class SoloLecturaTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    private function candado(bool $puesto): void
    {
        $app = new Container();
        $app->instance('config', new Repository(['red' => ['solo_lectura' => $puesto]]));
        Container::setInstance($app);
    }

    public function test_sin_configurar_se_pone_fuera_de_produccion_y_se_quita_en_produccion(): void
    {
        $this->assertTrue(SoloLectura::decidir(null, false));
        $this->assertTrue(SoloLectura::decidir('', false));
        $this->assertFalse(SoloLectura::decidir(null, true));
    }

    public function test_la_configuracion_lo_fuerza(): void
    {
        $this->assertTrue(SoloLectura::decidir(true, true), 'puesto a mano aun en producción');
        $this->assertFalse(SoloLectura::decidir(false, false), 'quitado a mano en dev, para el laboratorio');
        $this->assertTrue(SoloLectura::decidir('true', true));
        $this->assertFalse(SoloLectura::decidir('false', false));
    }

    public function test_routeros_deja_pasar_consultas(): void
    {
        foreach ([
            '/interface/print', '/ip/firewall/address-list/print', '/system/resource/print',
            '/interface/monitor-traffic', '/interface/ethernet/monitor', '/ppp/active/print',
            '/ping', '/tool/ping', '/cancel', '/ip/route/check',
        ] as $comando) {
            $this->assertTrue(SoloLectura::permiteRouterOs($comando), $comando);
        }
    }

    public function test_routeros_bloquea_cambios(): void
    {
        foreach ([
            '/ip/firewall/filter/add', '/ip/firewall/address-list/remove', '/ppp/secret/set',
            '/ppp/active/remove', '/system/script/add', '/system/script/run', '/import', '/export',
            '/system/reboot', '/user/set', '/file/remove', '/ip/proxy/set', '/interface/enable',
            '/interface/disable', '/queue/simple/move', '/ip/firewall/filter/unset', '/tool/bandwidth-test',
        ] as $comando) {
            $this->assertFalse(SoloLectura::permiteRouterOs($comando), $comando);
        }
    }

    public function test_routeros_revisa_el_comando_normalizado(): void
    {
        $this->assertSame('/ip/proxy/set', (new Request('/ip proxy set'))->getCommand());
        $this->assertFalse(SoloLectura::permiteRouterOs((new Request('/ip proxy set'))->getCommand()));
        $this->assertTrue(SoloLectura::permiteRouterOs((new Request('/interface print'))->getCommand()));
    }

    public function test_el_cliente_routeros_no_envia_un_cambio_con_el_candado_puesto(): void
    {
        $this->candado(true);

        $this->expectException(RedSoloLecturaException::class);
        $this->enviar(new Request('/ppp/secret/set'));
    }

    public function test_sin_candado_el_cliente_routeros_envia_como_el_original(): void
    {
        $this->candado(false);

        // Sin conexión real, el envío original truena al no tener con quién hablar (un Error, no el
        // bloqueo): eso prueba que el candado lo dejó pasar.
        $this->expectException(\Error::class);
        $this->enviar(new Request('/ppp/secret/set'));
    }

    public function test_smartolt_con_candado_solo_salen_consultas(): void
    {
        $this->candado(true);
        $respuestas = new MockHandler([new Response(200, [], '{"status":true}')]);
        $cliente = $this->smartOlt($respuestas);

        try {
            $cliente->post('onu/reboot/ABC123');
            $this->fail('El POST debió bloquearse');
        } catch (RedSoloLecturaException $e) {
            $this->assertStringContainsString('POST', $e->getMessage());
        }
        $this->assertCount(1, $respuestas, 'el POST no llegó a salir');

        $this->assertSame(200, $cliente->get('system/get_olts')->getStatusCode());
    }

    public function test_smartolt_sin_candado_deja_pasar_cambios(): void
    {
        $this->candado(false);
        $respuestas = new MockHandler([new Response(200)]);

        $this->assertSame(200, $this->smartOlt($respuestas)->post('onu/reboot/ABC123')->getStatusCode());
    }

    public function test_olt_huawei_no_escribe_con_el_candado_puesto(): void
    {
        $this->candado(true);
        $sesion = $this->createMock(SessionInterface::class);
        $sesion->expects($this->never())->method('write');

        $this->expectException(RedSoloLecturaException::class);
        (new HuaweiTransport([], $sesion))->execWrite('ont reset 2 0', 'y');
    }

    private function enviar(Request $request): void
    {
        $cliente = (new ReflectionClass(ClienteRouterOsSinConexion::class))->newInstanceWithoutConstructor();
        (new ReflectionMethod(ClienteRouterOs::class, 'send'))->invoke($cliente, $request);
    }

    private function smartOlt(MockHandler $respuestas): Client
    {
        $pila = HandlerStack::create($respuestas);
        $pila->push(SoloLectura::middlewareSoloConsultas('SmartOLT'), 'red-solo-lectura');

        return new Client(['handler' => $pila, 'base_uri' => 'https://ejemplo.smartolt.com/api/']);
    }
}

/** Sin conexión abierta: el cierre original de la librería tronaría y taparía lo que se prueba. */
class ClienteRouterOsSinConexion extends ClienteRouterOs
{
    public function __destruct()
    {
    }
}
