<?php

namespace Tests\Unit\VoIP;

use App\Models\Core\ApiIntegration;
use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\Adaptadores\OpenAIAdaptador;
use App\Modules\Addons\VoIP\Console\BotVozEscucharCommand;
use App\Modules\Addons\VoIP\Models\IaBotConfig;
use App\Modules\Addons\VoIP\Services\IABotCustomerService;
use App\Modules\Addons\VoIP\Services\Voz\AudioSocketServer;
use App\Modules\Addons\VoIP\Services\Voz\ConversacionBotService as C;
use App\Modules\Addons\VoIP\Services\Voz\VoiceSttService;
use App\Modules\Addons\VoIP\Services\Voz\VoiceTtsService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/** La asistente de voz (27-nov): quién llama, qué se le dice a la IA, voz y oído. Sin BD ni red. */
class AsistenteVozTest extends TestCase
{
    protected function setUp(): void
    {
        $app = new Container();
        Container::setInstance($app);
        $app->instance('config', new Repository(['voip' => ['bot_voz' => ['max_simultaneas' => 2]]]));
        Carbon::setTestNow(Carbon::parse('2026-11-27 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Container::setInstance(null);
    }

    // ── Quién llama: el número viaja en el UUID ─────────────────────────────

    public function test_el_numero_del_que_llama_sale_del_uuid(): void
    {
        $this->assertSame('5500000001', AudioSocketServer::numeroQueLlama('3f2a9c1e4b7d' . 'ca11' . 'ffffff5500000001'));
        $this->assertSame('5500000001', AudioSocketServer::numeroQueLlama('3f2a9c1e-4b7d-ca11-ffff-ff5500000001'));
        $this->assertSame('525500000001', AudioSocketServer::numeroQueLlama('3f2a9c1e4b7dca11ffff525500000001'));
    }

    public function test_sin_marca_o_sin_numero_no_hay_numero(): void
    {
        // Plan de marcado viejo: UUID al azar (aunque por casualidad termine en dígitos).
        $this->assertNull(AudioSocketServer::numeroQueLlama('3f2a9c1e4b7d4c11ffff525500000001'));
        // Llamada sin identificador: puras «f».
        $this->assertNull(AudioSocketServer::numeroQueLlama('3f2a9c1e4b7dca11ffffffffffffffff'));
        $this->assertNull(AudioSocketServer::numeroQueLlama(null));
        $this->assertNull(AudioSocketServer::numeroQueLlama('corto'));
    }

    public function test_el_plan_de_marcado_arma_un_uuid_valido_con_la_marca(): void
    {
        // Lo mismo que hace Asterisk con ${UUID():0:14}ca11-${BOT_NUM:0:4}-${BOT_NUM:4:12}.
        $uuid = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        $num = substr('ffffffffffffffff' . '5500000001', -16);
        $armado = substr($uuid, 0, 14) . AudioSocketServer::MARCA_NUMERO . '-' . substr($num, 0, 4) . '-' . substr($num, 4, 12);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $armado);
        $this->assertSame('5500000001', AudioSocketServer::numeroQueLlama($armado));
    }

    // ── Saludo ──────────────────────────────────────────────────────────────

    public function test_saludo_por_nombre_al_cliente_y_general_a_los_demas(): void
    {
        $c = new IaBotConfig([
            'greeting_customer' => 'Hola [nombre], bienvenido a Meganet.',
            'greeting_lead'     => 'Hola, bienvenido a Meganet. Soy la asistente.',
        ]);

        $this->assertSame('Hola Juan, bienvenido a Meganet.', BotVozEscucharCommand::saludo($c, ['found' => true, 'nombre_pila' => 'Juan']));
        $this->assertSame('Hola, bienvenido a Meganet.', BotVozEscucharCommand::saludo($c, ['found' => true, 'nombre_pila' => '']));
        $this->assertSame('Hola, bienvenido a Meganet. Soy la asistente.', BotVozEscucharCommand::saludo($c, null));
    }

    public function test_sin_saludo_de_clientes_se_usa_el_general(): void
    {
        $c = new IaBotConfig(['greeting_customer' => '  ', 'greeting_lead' => 'Hola [nombre], ¿en qué te ayudo?']);

        $this->assertSame('Hola, ¿en qué te ayudo?', BotVozEscucharCommand::saludo($c, ['found' => true, 'nombre_pila' => 'Ana']));
    }

    public function test_nombre_de_pila(): void
    {
        $this->assertSame('María', IABotCustomerService::nombreDePila('  MARÍA  JOSÉ '));
        $this->assertSame('', IABotCustomerService::nombreDePila(''));
        $this->assertSame('2026-11-30', IABotCustomerService::soloFecha('2026-11-30 23:59:59'));
        $this->assertNull(IABotCustomerService::soloFecha('30/11/2026'));
    }

    // ── Lo que recibe la IA ─────────────────────────────────────────────────

    public function test_bloque_de_un_cliente_que_debe(): void
    {
        $b = C::bloqueCliente([
            'found' => true, 'name' => 'Juan Pérez López', 'nombre_pila' => 'Juan', 'estado' => 'Bloqueado',
            'servicios' => [['plan' => 'Fibra 100 megas', 'precio' => 449.0]],
            'saldo' => -898.0, 'fecha_pago' => '2026-11-30', 'fecha_corte' => '2026-12-01', 'referencia' => 'MEG-00000123-20',
        ]);

        $this->assertStringContainsString('Juan Pérez López (dile Juan)', $b);
        $this->assertStringContainsString('suspendido', $b);
        $this->assertStringContainsString('Fibra 100 megas (449 pesos al mes)', $b);
        $this->assertStringContainsString('debe 898 pesos', $b);
        $this->assertStringContainsString('30 de noviembre', $b);
        $this->assertStringContainsString('1 de diciembre', $b);
        $this->assertStringContainsString('MEG-00000123-20', $b);
        $this->assertStringContainsString('no es el titular', $b);
    }

    public function test_bloque_de_saldo_a_favor_al_corriente_y_desconocido(): void
    {
        $this->assertStringContainsString('tiene 17 pesos a favor', C::bloqueCliente(['found' => true, 'name' => 'A', 'saldo' => 17]));
        $this->assertStringContainsString('al corriente', C::bloqueCliente(['found' => true, 'name' => 'A', 'saldo' => 0]));
        $this->assertStringContainsString('no está registrado', C::bloqueCliente(null));
        $this->assertStringContainsString('no está registrado', C::bloqueCliente(['found' => false]));
    }

    public function test_cantidades_y_fechas_como_se_dicen(): void
    {
        $this->assertSame('350 pesos', C::pesos(350.0));
        $this->assertSame('1,234.50 pesos', C::pesos(1234.5));
        $this->assertSame('30 de noviembre', C::fechaHablada('2026-11-30'));
        $this->assertSame('5 de enero de 2027', C::fechaHablada('2027-01-05'));
    }

    public function test_lo_que_no_se_puede_decir_se_quita(): void
    {
        $this->assertSame(
            'Claro. Revisa el módem. Luego reinícialo.',
            C::limpiarParaVoz("**Claro.**\n- Revisa el módem.\n2. Luego reinícialo. 😊 https://meganet.mx/x")
        );
    }

    public function test_el_analisis_queda_con_tipos_seguros(): void
    {
        $a = C::normalizarAnalisis([
            'resumen' => '  Quiere contratar 200 megas ', 'categoria' => 'ventas', 'resuelto' => 'false',
            'lead' => ['interesado' => 'true', 'nombre' => 'Ana', 'email' => 'no-es-correo', 'plan' => str_repeat('x', 300)],
        ]);

        $this->assertSame('Quiere contratar 200 megas', $a['resumen']);
        $this->assertSame('ventas', $a['categoria']);
        $this->assertFalse($a['resuelto']);
        $this->assertTrue($a['lead']['interesado']);
        $this->assertSame('', $a['lead']['email']);
        $this->assertSame(100, mb_strlen($a['lead']['plan']));
        $this->assertSame('otro', C::normalizarAnalisis(['categoria' => 'inventada'])['categoria']);
        $this->assertFalse(C::normalizarAnalisis([])['lead']['interesado']);
    }

    // ── Configuración ───────────────────────────────────────────────────────

    public function test_lineas_de_la_pantalla_o_del_env(): void
    {
        $this->assertSame(2, (new IaBotConfig())->lineasEfectivas());
        $this->assertSame(3, (new IaBotConfig(['lineas' => 3]))->lineasEfectivas());
        $this->assertSame(IaBotConfig::LINEAS_MAX, (new IaBotConfig(['lineas' => 99]))->lineasEfectivas());
    }

    // ── Voz ─────────────────────────────────────────────────────────────────

    public function test_la_voz_elegida_se_usa_y_lo_invalido_cae_a_lo_de_siempre(): void
    {
        $this->assertSame(['voz' => 'onyx', 'velocidad' => 1.1, 'tono' => 'formal'], VoiceTtsService::normalizarVoz(['voz' => 'onyx', 'velocidad' => 1.1, 'tono' => 'formal']));
        $this->assertSame(['voz' => 'nova', 'velocidad' => 1.25, 'tono' => 'calida'], VoiceTtsService::normalizarVoz(['voz' => 'x', 'velocidad' => 9, 'tono' => 'x']));
        // Como antes: solo el nombre de la voz.
        $this->assertSame('shimmer', VoiceTtsService::normalizarVoz('shimmer')['voz']);
    }

    public function test_la_respuesta_se_parte_en_oraciones_para_pedirlas_en_paralelo(): void
    {
        $this->assertSame(
            ['Claro, Francisco.', 'Tienes un saldo de 449 pesos.', '¿Te doy la referencia para pagar?'],
            VoiceTtsService::partirEnFrases('Claro, Francisco. Tienes un saldo de 449 pesos. ¿Te doy la referencia para pagar?')
        );
        $this->assertSame(['Sí. Te comunico con una persona.'], VoiceTtsService::partirEnFrases('Sí. Te comunico con una persona.'));
        $this->assertCount(2, VoiceTtsService::partirEnFrases('Uno dos tres. Cuatro cinco seis. Siete ocho nueve.', 2));
        $this->assertSame(['Hola'], VoiceTtsService::partirEnFrases('Hola'));
    }

    public function test_las_instrucciones_de_habla_van_al_driver(): void
    {
        $o = VoiceTtsService::opcionesDriver(VoiceTtsService::normalizarVoz(['voz' => 'nova', 'tono' => 'tranquila']));

        $this->assertSame('nova', $o['voice']);
        $this->assertStringContainsString('español de México', $o['instructions']);
        $this->assertTrue($o['sin_duracion']);
        $this->assertTrue(VoiceTtsService::soportaTono('gpt-4o-mini-tts'));
        $this->assertFalse(VoiceTtsService::soportaTono('tts-1'));
    }

    // ── Oído ────────────────────────────────────────────────────────────────

    public function test_formato_e_idioma_segun_el_modelo(): void
    {
        $this->assertSame('verbose_json', VoiceSttService::formatoPara('whisper-1'));
        $this->assertSame('json', VoiceSttService::formatoPara('gpt-4o-mini-transcribe'));
        $this->assertSame('es', VoiceSttService::idioma('es-MX'));
        $this->assertSame('es', VoiceSttService::idioma(''));
        $this->assertTrue(VoiceSttService::esAlucinacion('Subtítulos realizados por la comunidad de Amara.org'));
        $this->assertFalse(VoiceSttService::esAlucinacion('No tengo internet'));
    }

    // ── IA: razonamiento bajo solo cuando se pide ───────────────────────────

    public function test_razonamiento_bajo_solo_si_se_pide_y_solo_en_gpt5_en_adelante(): void
    {
        $payload = fn (string $modelo, array $opciones) => $this->limites($modelo, $opciones);

        $this->assertSame('low', $payload('gpt-6-luna', ['razonamiento' => 'low'])['reasoning_effort'] ?? null);
        $this->assertArrayNotHasKey('reasoning_effort', $payload('gpt-6-luna', []));
        $this->assertArrayNotHasKey('reasoning_effort', $payload('gpt-4o-mini', ['razonamiento' => 'low']));
        $this->assertSame(0.3, $payload('gpt-4o-mini', ['temperatura' => 0.3])['temperature']);
    }

    private function limites(string $modelo, array $opciones): array
    {
        $proveedor = new IAProveedor(['nombre' => 'x', 'driver' => 'openai', 'modelo_default' => $modelo, 'config_extra' => []]);
        $proveedor->setRelation('integracionHub', new ApiIntegration());
        $adaptador = (new \ReflectionClass(OpenAIAdaptador::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($adaptador, 'proveedor'))->setValue($adaptador, $proveedor);

        $payload = ['model' => $modelo];
        $m = new \ReflectionMethod($adaptador, 'aplicarLimites');
        $m->invokeArgs($adaptador, [&$payload, $opciones]);

        return $payload;
    }
}
