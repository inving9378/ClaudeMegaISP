<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\JarvisService;
use App\Modules\Addons\Roadmap\Services\RevisorService;
use App\Modules\Addons\Roadmap\Support\IaSupervisor;
use App\Services\Core\SaludIA;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

/**
 * #1076 — IA económica del supervisor: quién elige el modelo, qué viaja a la API, qué queda en el
 * log de costo y cuándo se paga una segunda opinión cara.
 *
 * Sin API real (Http::fake) y sin tocar el esquema de megaisp_test: las cuatro tablas que usa la
 * pieza se crean como TEMPORARY (no hacen commit implícito y mueren con la conexión), y todo corre
 * dentro de DatabaseTransactions. El archivo de salud de la IA y el log `claude` se desvían a
 * archivos temporales para no ensuciar los reales.
 */
class IaSupervisorEconomicoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private string $log;

    private string $salud;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringEndsWith('_test', (string) DB::selectOne('select database() d')->d);

        foreach (['ia_asignaciones', 'api_integrations', 'api_integration_providers', 'circuito_revisiones'] as $t) {
            DB::statement("DROP TEMPORARY TABLE IF EXISTS {$t}");
        }
        Schema::create('api_integration_providers', function (Blueprint $t) {
            $t->temporary();
            $t->id();
            $t->string('slug');
            $t->string('name')->nullable();
            $t->string('type')->nullable();
            $t->string('driver')->nullable();
            $t->boolean('soporta_imagenes')->default(false);
            $t->boolean('soporta_pdf')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('api_integrations', function (Blueprint $t) {
            $t->temporary();
            $t->id();
            $t->unsignedBigInteger('company_id')->default(1);
            $t->string('provider');
            $t->string('type')->nullable();
            $t->string('slug')->nullable();
            $t->string('name')->nullable();
            $t->text('encrypted_value')->nullable();
            $t->json('config')->nullable();
            $t->boolean('active')->default(true);
            $t->boolean('is_default_for_provider')->default(false);
            $t->string('last_validation_status')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('ia_asignaciones', function (Blueprint $t) {
            $t->temporary();
            $t->id();
            $t->string('clave');
            $t->unsignedBigInteger('api_integration_id')->nullable();
            $t->string('modelo')->nullable();
            $t->timestamps();
        });
        Schema::create('circuito_revisiones', function (Blueprint $t) {
            $t->temporary();
            $t->id();
            $t->unsignedBigInteger('roadmap_item_id')->nullable();
            $t->string('veredicto')->nullable();
            $t->boolean('en_alcance')->default(false);
            $t->string('categoria_escalada')->nullable();
            $t->string('confianza')->nullable();
            $t->text('motivo')->nullable();
            $t->text('riesgos')->nullable();
            $t->string('modelo')->nullable();
            $t->text('decisor_ctx')->nullable();
            $t->integer('tokens_in')->nullable();
            $t->integer('tokens_out')->nullable();
            $t->boolean('aplicado')->default(false);
            $t->string('actor')->nullable();
            $t->timestamps();
        });

        $this->log   = tempnam(sys_get_temp_dir(), 'claude-log-');
        $this->salud = tempnam(sys_get_temp_dir(), 'ia-salud-');
        config(['logging.channels.claude' => ['driver' => 'single', 'path' => $this->log, 'level' => 'debug']]);
        app('log')->forgetChannel('claude');
        SaludIA::usarArchivo($this->salud);
        IaSupervisor::forzar([]);
    }

    protected function tearDown(): void
    {
        SaludIA::usarArchivo(null);
        IaSupervisor::forzar([]);
        @unlink($this->log);
        @unlink($this->salud);
        parent::tearDown();
    }

    // ── quién elige el modelo ───────────────────────────────────────────────────────────────

    public function test_sin_asignacion_cada_pieza_usa_su_ahorro_por_defecto(): void
    {
        $this->assertSame(config('circuito.ia.modelos.revisor'), IaSupervisor::modelo(IaSupervisor::REVISOR));
        $this->assertSame(config('circuito.ia.modelos.valvula'), IaSupervisor::modelo(IaSupervisor::VALVULA));
        $this->assertSame(config('circuito.ia.modelos.segunda_opinion'), IaSupervisor::modelo(IaSupervisor::SEGUNDA_OPINION));

        $e = IA::efectivo('circuito.revisor');
        $this->assertSame('por_defecto', $e['origen']);
        $this->assertSame(config('circuito.ia.modelos.revisor'), $e['modelo']);
        $this->assertNull($e['problema']);
        $this->assertNull($e['aviso']);
    }

    public function test_la_asignacion_en_modulos_ia_manda(): void
    {
        $anthropic = $this->integracion('anthropic', 'claude');
        $this->asignar('circuito.revisor', $anthropic, 'claude-haiku-5-5');

        $this->assertSame('claude-haiku-5-5', IaSupervisor::modelo(IaSupervisor::REVISOR));
        $this->assertSame('propia', IA::efectivo('circuito.revisor')['origen']);
        // Las demás piezas no se mueven.
        $this->assertSame(config('circuito.ia.modelos.briefs'), IaSupervisor::modelo(IaSupervisor::BRIEFS));
    }

    public function test_una_asignacion_a_otro_proveedor_no_se_usa_y_la_pantalla_lo_avisa(): void
    {
        $openai = $this->integracion('openai', 'openai');
        $this->asignar('circuito.briefs', $openai, 'gpt-4o');

        $this->assertSame(config('circuito.ia.modelos.briefs'), IaSupervisor::modelo(IaSupervisor::BRIEFS));

        $e = IA::efectivo('circuito.briefs');
        $this->assertSame('por_defecto', $e['origen']);
        $this->assertNotNull($e['aviso']);
        $this->assertStringContainsString('Claude', $e['aviso']);
    }

    public function test_la_ia_general_del_circuito_da_la_llave_y_cada_pieza_conserva_su_ahorro(): void
    {
        $anthropic = $this->integracion('anthropic', 'claude');
        $this->asignar(IA::claveGeneral('Circuito'), $anthropic, 'claude-opus-4-7');

        // Sin modelo propio, la pieza usa el primero de su lista (su ahorro), no el de la general.
        $this->assertSame(config('circuito.ia.modelos.valvula'), IaSupervisor::modelo(IaSupervisor::VALVULA));

        // Con solo modelo propio, usa ese con la llave de la general.
        DB::table('ia_asignaciones')->insert(['clave' => 'circuito.valvula', 'api_integration_id' => null,
            'modelo' => 'claude-sonnet-5-5', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('claude-sonnet-5-5', IaSupervisor::modelo(IaSupervisor::VALVULA));
    }

    // ── qué viaja a la API y qué queda en el log ────────────────────────────────────────────

    public function test_llamar_adapta_los_parametros_y_deja_pieza_y_costo_en_el_log(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->respuesta('{"veredicto":"mencion","razon":"x"}'))]);
        IaSupervisor::forzar([IaSupervisor::VALVULA => 'claude-haiku-5-5']);

        $r = IaSupervisor::llamar(IaSupervisor::VALVULA, [
            'max_tokens' => 300, 'system' => 'corto', 'temperature' => 0,
            'messages' => [['role' => 'user', 'content' => 'hola']],
        ], ['item' => 77]);

        $this->assertSame('{"veredicto":"mencion","razon":"x"}', $r['texto']);
        Http::assertSent(function (Request $req) {
            $b = $req->data();

            return $b['model'] === 'claude-haiku-5-5' && ! array_key_exists('temperature', $b)
                && $b['thinking'] === ['type' => 'disabled'] && $b['output_config'] === ['effort' => 'low'];
        });

        $linea = (string) file_get_contents($this->log);
        $this->assertStringContainsString('"pieza":"circuito.valvula"', $linea);
        $this->assertStringContainsString('"item":77', $linea);
        $this->assertMatchesRegularExpression('/"costo_usd":[0-9.eE-]+/', $linea);
        $this->assertMatchesRegularExpression('/"costo_mxn":[0-9.eE-]+/', $linea);
    }

    // ── segunda opinión cara solo cuando el barato duda ─────────────────────────────────────

    public function test_revisor_con_confianza_baja_pide_segunda_opinion_y_lo_marca_para_el_log(): void
    {
        Http::fakeSequence('api.anthropic.com/*')
            ->push($this->respuesta('{"veredicto":"escala","razon":"no sé","riesgos":[],"categoria_escalada":"duda","confianza":"baja"}'))
            ->push($this->respuesta('{"veredicto":"autoriza","razon":"es un texto","riesgos":[],"categoria_escalada":null,"confianza":"alta"}'));

        $v = app(RevisorService::class)->revisar($this->item('Cambiar el texto de un botón de la Torre'));

        $this->assertSame('autoriza', $v['veredicto']);
        $this->assertSame(config('circuito.ia.modelos.revisor'), $v['_escalado']['de']);
        $this->assertSame(config('circuito.ia.modelos.segunda_opinion'), $v['_escalado']['a']);
        $this->assertStringContainsString('confianza baja', $v['_escalado']['motivo']);

        $modelos = collect(Http::recorded())->map(fn ($par) => $par[0]->data()['model'])->all();
        $this->assertSame([config('circuito.ia.modelos.revisor'), config('circuito.ia.modelos.segunda_opinion')], $modelos);
    }

    public function test_revisor_en_frontera_dura_no_gasta_opus(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->respuesta(
            '{"veredicto":"escala","razon":"mueve saldos","riesgos":[],"categoria_escalada":"dinero","confianza":"baja"}'))]);

        $v = app(RevisorService::class)->revisar($this->item('Cambiar el texto de un botón de la Torre'));

        $this->assertSame('escala', $v['veredicto']);
        $this->assertArrayNotHasKey('_escalado', $v);
        Http::assertSentCount(1);
    }

    public function test_un_rechazo_del_revisor_escala_sin_juicio_y_sin_segunda_opinion(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(array_merge(
            $this->respuesta('{"veredicto":"autoriza","confianza":"alta"}'),
            ['stop_reason' => 'refusal', 'stop_details' => ['category' => 'cyber']]
        ))]);

        $v = app(RevisorService::class)->revisar($this->item('Cambiar el texto de un botón de la Torre'));

        $this->assertSame('escala', $v['veredicto']);
        $this->assertSame(RevisorService::CAT_ILEGIBLE, $v['categoria_escalada']);
        Http::assertSentCount(1);
    }

    public function test_brief_con_recomendacion_de_confianza_baja_pide_segunda_opinion(): void
    {
        $this->sinFronteraDura();
        Http::fakeSequence('api.anthropic.com/*')
            ->push($this->respuesta($this->preguntas('baja', false)))
            ->push($this->respuesta($this->preguntas('alta', false)));

        $r = app(RevisorService::class)->proponerPreguntas($this->item('Ordenar la lista de la Torre'));

        $this->assertSame(config('circuito.ia.modelos.segunda_opinion'), $r['modelo']);
        $this->assertSame('alta', $r['preguntas'][0]['opciones'][0]['confianza']);
        $this->assertSame(config('circuito.ia.modelos.briefs'), $r['escalado']['de']);
        $this->assertSame('respondió', $r['escalado']['resultado']);
        Http::assertSentCount(2);
    }

    public function test_brief_no_escala_si_la_pregunta_es_de_irving_o_si_hay_frontera_dura(): void
    {
        $this->sinFronteraDura();
        Http::fake(['api.anthropic.com/*' => Http::response($this->respuesta($this->preguntas('baja', true)))]);
        $r = app(RevisorService::class)->proponerPreguntas($this->item('Ordenar la lista de la Torre'));
        $this->assertNull($r['escalado']);
        Http::assertSentCount(1);

        $this->mock(JarvisService::class, fn ($m) => $m->shouldReceive('fronteraDuraDeItemDetalle')->andReturn(['categoria' => 'dinero']));
        Http::fake(['api.anthropic.com/*' => Http::response($this->respuesta($this->preguntas('baja', false)))]);
        $r = app(RevisorService::class)->proponerPreguntas($this->item('Ordenar la lista de la Torre'));
        $this->assertNull($r['escalado']);
    }

    public function test_brief_sin_recomendacion_se_rehace_con_opus_aunque_sea_de_irving(): void
    {
        $sinRecomendar = json_encode([[
            'pregunta' => '¿Qué precio se cobra?', 'requiere_irving' => true, 'fase' => null,
            'opciones' => [
                ['texto' => 'Opción 1: subir 10 % — Pro: margen. Contra: quejas', 'recomendada' => false, 'confianza' => 'media', 'reversible' => true],
                ['texto' => 'Opción 2: dejarlo igual — Pro: estable. Contra: margen bajo', 'recomendada' => false, 'confianza' => 'media', 'reversible' => true],
            ],
        ]], JSON_UNESCAPED_UNICODE);
        $this->mock(JarvisService::class, fn ($m) => $m->shouldReceive('fronteraDuraDeItemDetalle')->andReturn(['categoria' => 'dinero']));
        Http::fakeSequence('api.anthropic.com/*')
            ->push($this->respuesta($sinRecomendar))
            ->push($this->respuesta($this->preguntas('media', true)));

        $r = app(RevisorService::class)->proponerPreguntas($this->item('Precio del plan'));

        $this->assertStringContainsString('sin recomendación', $r['escalado']['motivo']);
        $this->assertSame(config('circuito.ia.modelos.segunda_opinion'), $r['modelo']);
        Http::assertSentCount(2);
    }

    public function test_brief_con_recomendacion_generica_se_rehace_con_opus(): void
    {
        $this->sinFronteraDura();
        $generico = json_encode([[
            'pregunta' => '¿Qué hacemos?', 'requiere_irving' => false, 'fase' => null,
            'opciones' => [
                ['texto' => 'Opción 1: hacerlo', 'recomendada' => true, 'confianza' => 'alta', 'reversible' => true],
                ['texto' => 'Opción 2: no hacerlo', 'recomendada' => false, 'confianza' => 'alta', 'reversible' => true],
            ],
        ]], JSON_UNESCAPED_UNICODE);
        Http::fakeSequence('api.anthropic.com/*')
            ->push($this->respuesta($generico))
            ->push($this->respuesta($this->preguntas('alta', false)));

        $r = app(RevisorService::class)->proponerPreguntas($this->item('Ordenar la lista de la Torre'));

        $this->assertStringContainsString('genérica', $r['escalado']['motivo']);
        Http::assertSentCount(2);
    }

    public function test_brief_c_sin_recomendacion_o_generico_pide_opus(): void
    {
        $m = new \ReflectionMethod(RevisorService::class, 'motivoRehacerBriefC');
        $svc = app(RevisorService::class);
        $completo = "**Qué se decide** X.\n**Opciones** a / b.\n**Riesgos** - r\n**Recomendación** Opción a, porque es reversible y aditiva en dev.";

        $this->assertNull($m->invoke($svc, $completo, 'claude-sonnet-5-5'));
        $this->assertStringContainsString('sin recomendación', $m->invoke($svc, "**Qué se decide** X.\n**Opciones** a / b.", 'claude-sonnet-5-5'));
        $this->assertStringContainsString('genérica', $m->invoke($svc, "**Opciones** a / b.\n**Recomendación** La a.", 'claude-sonnet-5-5'));
        $this->assertNull($m->invoke($svc, "**Opciones** a / b.", 'claude-opus-4-7'), 'Si ya es Opus no se vuelve a pedir.');
    }

    public function test_brief_sin_permiso_de_escalar_no_llama_dos_veces(): void
    {
        $this->sinFronteraDura();
        Http::fake(['api.anthropic.com/*' => Http::response($this->respuesta($this->preguntas('baja', false)))]);

        $r = app(RevisorService::class)->proponerPreguntas($this->item('Ordenar la lista de la Torre'), false);

        $this->assertNull($r['escalado']);
        Http::assertSentCount(1);
    }

    public function test_el_escalado_queda_como_entrada_del_log_del_item(): void
    {
        $m = new \ReflectionMethod(RevisorService::class, 'conEscalado');
        $log = $m->invoke(null, [['evento' => 'previo']], [
            'pieza' => IaSupervisor::BRIEFS, 'de' => 'claude-sonnet-5-5', 'a' => 'claude-opus-4-7',
            'motivo' => 'recomendó con confianza baja (q1)', 'resultado' => 'respondió',
        ], 'revisor:brief-async(#437)');

        $this->assertCount(2, $log);
        $this->assertSame('escalado_a_opus', $log[1]['evento']);
        $this->assertSame('claude-opus-4-7', $log[1]['a']);
        $this->assertSame('recomendó con confianza baja (q1)', $log[1]['motivo']);
        $this->assertSame('revisor:brief-async(#437)', $log[1]['por']);
    }

    // ── ayudas ──────────────────────────────────────────────────────────────────────────────

    private function integracion(string $proveedor, string $driver): int
    {
        DB::table('api_integration_providers')->insert(['slug' => $proveedor, 'name' => $proveedor, 'type' => 'ia',
            'driver' => $driver, 'created_at' => now(), 'updated_at' => now()]);

        return (int) DB::table('api_integrations')->insertGetId(['provider' => $proveedor, 'type' => 'ia',
            'slug' => "{$proveedor}-prueba", 'name' => "{$proveedor} prueba", 'encrypted_value' => 'cifrada',
            'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function asignar(string $clave, int $integracion, string $modelo): void
    {
        DB::table('ia_asignaciones')->insert(['clave' => $clave, 'api_integration_id' => $integracion,
            'modelo' => $modelo, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function item(string $titulo): RoadmapItem
    {
        return (new RoadmapItem())->forceFill([
            'title' => $titulo, 'modulo' => 'Torre', 'nivel_riesgo' => 'B',
            'prompt' => 'Ajuste visual acotado y reversible en una pantalla interna.',
        ]);
    }

    private function sinFronteraDura(): void
    {
        $this->mock(JarvisService::class, fn ($m) => $m->shouldReceive('fronteraDuraDeItemDetalle')->andReturn(['categoria' => null]));
    }

    private function preguntas(string $confianza, bool $deIrving): string
    {
        return json_encode([[
            'pregunta' => '¿En qué orden se muestra la lista?', 'requiere_irving' => $deIrving, 'fase' => null,
            'opciones' => [
                ['texto' => 'Opción 1: por fecha — Pro: simple. Contra: nada', 'recomendada' => true, 'confianza' => $confianza, 'reversible' => true],
                ['texto' => 'Opción 2: por nombre — Pro: x. Contra: y', 'recomendada' => false, 'confianza' => 'media', 'reversible' => true],
            ],
        ]], JSON_UNESCAPED_UNICODE);
    }

    private function respuesta(string $texto): array
    {
        return [
            'id' => 'msg_prueba', 'type' => 'message', 'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => $texto]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 80],
        ];
    }
}
