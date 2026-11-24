<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\CobranzaBlaster\Services\CobranzaTtsService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/** Voz de Campañas/Blaster: la nota es lo que se lee, con la voz, velocidad y tono de la campaña. Sin BD ni red. */
class VozCampanasTest extends TestCase
{
    protected function setUp(): void
    {
        $app = new Container();
        Container::setInstance($app);
        $app->instance('config', new Repository([
            'cobranza' => ['blaster_tts_voice' => 'nova', 'blaster_audio_base_path' => '/tmp/'],
        ]));
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function test_sin_nota_se_lee_el_mensaje_de_siempre(): void
    {
        $texto = (new CobranzaTtsService())->textoCobranza(null, 'Ana Ruiz', 1234.5, '30/11/2026', 'C-987');

        $this->assertSame(
            'Estimado Ana Ruiz, le habla Meganet Telecomunicaciones. Su saldo vencido es de 1,234.50 pesos. '
            . 'Le solicitamos realizar su pago antes del 30/11/2026. Su número de cliente es 9 8 7. Gracias por su atención.',
            $texto
        );
    }

    public function test_la_nota_se_lee_con_los_datos_del_cliente(): void
    {
        $texto = (new CobranzaTtsService())->textoCobranza(
            '  Hola {nombre}, debe {saldo} pesos; cliente {numero_cliente}, antes del {fecha}.  ',
            'Ana Ruiz', 99, '01/12/2026', '42'
        );

        $this->assertSame('Hola Ana Ruiz, debe 99.00 pesos; cliente 4 2, antes del 01/12/2026.', $texto);
    }

    public function test_aviso_se_escucha_tal_cual(): void
    {
        $tts = new CobranzaTtsService();

        $this->assertSame('Corte el lunes {nombre}.', $tts->textoDeEjemplo(' Corte el lunes {nombre}. ', 'corte', 'x'));
        $this->assertStringContainsString('Juan Pérez', $tts->textoDeEjemplo('Hola {nombre}', 'cobranza', 'x'));
    }

    public function test_voz_y_velocidad_de_la_campana(): void
    {
        $cuerpo = (new CobranzaTtsService())->cuerpo('Hola', 'tts-1', ['voz' => 'onyx', 'velocidad' => 1.2]);

        $this->assertSame('onyx', $cuerpo['voice']);
        $this->assertSame(1.2, $cuerpo['speed']);
        $this->assertArrayNotHasKey('instructions', $cuerpo);
    }

    public function test_sin_opciones_queda_como_antes(): void
    {
        $cuerpo = (new CobranzaTtsService())->cuerpo('Hola', 'tts-1', []);

        $this->assertSame(['model' => 'tts-1', 'input' => 'Hola', 'voice' => 'nova', 'response_format' => 'mp3', 'speed' => 0.95], $cuerpo);
    }

    public function test_el_tono_solo_va_con_modelos_que_lo_entienden(): void
    {
        $tts = new CobranzaTtsService();

        $this->assertArrayNotHasKey('instructions', $tts->cuerpo('Hola', 'tts-1-hd', ['tono' => 'amable']));
        $this->assertStringContainsString('amable', $tts->cuerpo('Hola', 'gpt-4o-mini-tts', ['tono' => 'amable'])['instructions']);
    }

    public function test_valores_fuera_de_rango_no_llegan_al_servicio(): void
    {
        $cuerpo = (new CobranzaTtsService())->cuerpo('Hola', 'gpt-4o-mini-tts', ['voz' => 'otra', 'velocidad' => 9, 'tono' => 'gritón']);

        $this->assertSame('nova', $cuerpo['voice']);
        $this->assertSame(CobranzaTtsService::VELOCIDAD_MAX, $cuerpo['speed']);
        $this->assertArrayNotHasKey('instructions', $cuerpo);
    }
}
