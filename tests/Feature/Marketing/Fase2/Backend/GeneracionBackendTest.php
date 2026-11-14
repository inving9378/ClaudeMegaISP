<?php

namespace Tests\Feature\Marketing\Fase2\Backend;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\Lead;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Addons\Marketing\Jobs\GenerarEstrategiaCampanaJob;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Services\Campanas360\ClienteIACampana;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase2\Backend\Concerns\EscenarioBackendFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 2 · backend — generar con IA (§2.4.7–§2.4.11): estrategia, piezas exactas por canal, reintento único, errores
 * en español, IA no configurada, 409, edición y regeneración de UNA pieza. La IA es un doble (ninguna llamada real);
 * la cola de pruebas es `sync`, así que los trabajos corren dentro de la petición.
 */
class GeneracionBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase2;

    private const TODOS = ['facebook', 'instagram', 'reels', 'video', 'anuncios_meta', 'whatsapp'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarNichos();
    }

    /** Respuestas de la IA para alcance `todo` con los 6 canales, en el orden en que se piden. */
    private function respuestasTodo(): array
    {
        return [
            $this->estrategiaValida(self::TODOS),
            $this->respuestaPiezas('reel_guion', ['A']),
            $this->respuestaPiezas('reel_guion', ['B']),
            $this->respuestaPiezas('video_guion', ['A']),
            $this->respuestaPiezas('copy_facebook', ['A', 'B']),
            $this->respuestaPiezas('copy_instagram', ['A', 'B']),
            $this->respuestaPiezas('variante_anuncio', ['A', 'B', 'C']),
            $this->respuestaPiezas('mensaje_whatsapp', ['A', 'B', 'C']),
        ];
    }

    public function test_sin_ia_asignada_responde_422_y_no_despacha(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);

        // IA real del módulo central: en megaisp_test nadie tiene IA asignada.
        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/generar", ['alcance' => 'todo'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'ia_no_configurada')
            ->assertJsonPath('mensaje', 'Falta asignar una IA a «Estrategia de campañas 360» en Integraciones → Módulos IA. Pídeselo a Irving.');

        Queue::assertNothingPushed();
        $this->assertSame('sin_generar', $c->fresh()->estrategia_estado);
    }

    public function test_falta_solo_la_ia_de_piezas(): void
    {
        $this->falsearIA([], [ClienteIACampana::PIEZAS => false]);
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/generar", ['alcance' => 'todo'])
            ->assertStatus(422)->assertJsonPath('error', 'ia_no_configurada');
        $this->assertSame([], $this->iaFalsa->llamadas);
    }

    public function test_generar_todo_crea_estrategia_y_exactamente_las_piezas_por_canal(): void
    {
        $this->falsearIA($this->respuestasTodo());
        $admin = $this->admin();
        $lead = Lead::create(['company_id' => Empresa::PRINCIPAL_ID, 'full_name' => 'Persona Secreta Xyz', 'status' => 'new', 'captured_at' => now()]);
        $c = $this->crearCampana360($admin, ['canales' => self::TODOS, 'presupuesto_mxn' => '10000.01']);

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/generar", ['alcance' => 'todo'])
            ->assertStatus(202)->assertJsonPath('ok', true);

        $this->assertCount(8, $this->iaFalsa->llamadas, 'estrategia + 1 por reel + 1 por cada otro tipo');
        $this->assertSame(ClienteIACampana::ESTRATEGIA, $this->iaFalsa->llamadas[0]['clave']);
        foreach (array_slice($this->iaFalsa->llamadas, 1) as $llamada) {
            $this->assertSame(ClienteIACampana::PIEZAS, $llamada['clave']);
        }
        foreach ($this->iaFalsa->llamadas as $llamada) {
            $this->assertStringContainsString('Fibra 500 prueba', $llamada['mensaje']);
            $this->assertStringNotContainsString($lead->full_name, $llamada['mensaje']);
        }

        $ficha = $this->actingAs($admin)->getJson("/api/marketing/campanas-360/{$c->id}")->assertOk();
        $this->assertSame('lista', $ficha->json('data.estrategia_estado'));
        $this->assertSame('lista', $ficha->json('data.generacion.estado'));
        $this->assertSame(13, $ficha->json('data.generacion.piezas_total'));
        $this->assertSame(13, $ficha->json('data.generacion.piezas_listas'));

        $esperado = ['reel_guion' => ['A', 'B'], 'video_guion' => ['A'], 'copy_facebook' => ['A', 'B'], 'copy_instagram' => ['A', 'B'],
                     'mensaje_whatsapp' => ['A', 'B', 'C'], 'variante_anuncio' => ['A', 'B', 'C']];
        foreach ($esperado as $pieza => $variantes) {
            $this->assertSame($variantes, collect($ficha->json("data.piezas.{$pieza}"))->pluck('variante')->all(), $pieza);
        }
        $this->assertSame('9:16', $ficha->json('data.piezas.reel_guion.0.contenido.formato'));
        $this->assertSame('16:9', $ficha->json('data.piezas.video_guion.0.contenido.formato'));
        $this->assertSame('seguimiento', $ficha->json('data.piezas.mensaje_whatsapp.1.contenido.uso'));

        $reparto = $ficha->json('data.estrategia.reparto_presupuesto');
        $suma = '0.00';
        foreach ($reparto as $r) {
            $suma = bcadd($suma, $r['monto_mxn'], 2);
        }
        $this->assertSame('10000.01', $suma);

        $fila = GeneratedContent::query()->where('source_campaign_id', $c->id)->where('pieza', 'copy_facebook')->first();
        $this->assertSame('copy', $fila->type);
        $this->assertSame('ia:modelo-falso', $fila->generation_engine);
        $this->assertSame('0.0000', (string) $fila->generation_cost_usd);
        $this->assertStringContainsString('Texto FB A', $fila->output_text);
    }

    public function test_solo_los_canales_elegidos_generan_piezas(): void
    {
        $this->falsearIA([$this->estrategiaValida(['facebook', 'whatsapp']),
            $this->respuestaPiezas('copy_facebook', ['A', 'B']), $this->respuestaPiezas('mensaje_whatsapp', ['A', 'B', 'C'])]);
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['facebook', 'whatsapp']]);

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/generar", ['alcance' => 'todo'])->assertStatus(202);

        $piezas = GeneratedContent::query()->where('source_campaign_id', $c->id)->pluck('pieza')->countBy()->all();
        $this->assertSame(['copy_facebook' => 2, 'mensaje_whatsapp' => 3], $piezas);
        $this->assertCount(3, $this->iaFalsa->llamadas);
    }

    public function test_estrategia_invalida_reintenta_una_vez_y_luego_queda_lista(): void
    {
        $mala = $this->estrategiaValida(['facebook']);
        $mala['mensajes_clave'] = ['solo uno'];
        $this->falsearIA(['esto no es json', $this->estrategiaValida(['facebook'])]);
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['facebook']]);

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/generar", ['alcance' => 'estrategia'])->assertStatus(202);

        $this->assertCount(2, $this->iaFalsa->llamadas);
        $this->assertStringContainsString('Tu respuesta no cumplió', $this->iaFalsa->llamadas[1]['mensaje']);
        $this->assertSame('lista', $c->fresh()->estrategia_estado);
        $this->assertSame(0, GeneratedContent::query()->where('source_campaign_id', $c->id)->count(), 'alcance estrategia no crea piezas');
    }

    public function test_estrategia_invalida_dos_veces_queda_en_error_sin_piezas(): void
    {
        $mala = $this->estrategiaValida(['facebook']);
        $mala['mensajes_clave'] = ['solo uno'];
        $this->falsearIA([$mala, '{"roto":']);
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['facebook']]);

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/generar", ['alcance' => 'todo'])->assertStatus(202)
            ->assertJsonPath('generacion.estado', 'con_errores');

        $c->refresh();
        $this->assertSame('error', $c->estrategia_estado);
        $this->assertSame('La IA no devolvió una estrategia válida dos veces. Intenta generar de nuevo.', $c->estrategia_error);
        $this->assertCount(2, $this->iaFalsa->llamadas);
        $this->assertSame(0, GeneratedContent::query()->where('source_campaign_id', $c->id)->count());
    }

    public function test_ia_que_se_desconfigura_a_media_generacion_deja_mensaje_claro(): void
    {
        $this->falsearIA([new IANoConfigurada('sin IA')]);
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['facebook']]);

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/generar", ['alcance' => 'todo'])->assertStatus(202);

        $this->assertStringStartsWith('Falta asignar una IA a «Estrategia de campañas 360»', $c->fresh()->estrategia_error);
    }

    public function test_un_tipo_que_falla_no_tumba_a_los_demas(): void
    {
        $malo = $this->respuestaPiezas('copy_instagram', ['A', 'B']);
        $malo['piezas'][1]['contenido']['texto'] = 'Hola {{brand.phone}}';
        $this->falsearIA([$this->estrategiaValida(['facebook', 'instagram']),
            $this->respuestaPiezas('copy_facebook', ['A', 'B']), $malo, $malo]);
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['facebook', 'instagram']]);

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/generar", ['alcance' => 'todo'])->assertStatus(202);

        $p = $this->actingAs($admin)->getJson("/api/marketing/campanas-360/{$c->id}/progreso")->assertOk();
        $this->assertSame('con_errores', $p->json('generacion.estado'));
        $this->assertSame(1, $p->json('generacion.piezas_error'));
        $estados = collect($p->json('piezas'))->mapWithKeys(fn ($x) => [$x['pieza'] . $x['variante'] => $x['estado']])->all();
        $this->assertSame(['copy_facebookA' => 'lista', 'copy_facebookB' => 'lista', 'copy_instagramA' => 'lista', 'copy_instagramB' => 'error'], $estados);
        $this->assertSame('La IA no entregó esta pieza válida: no puede llevar {{ }}. Regenérala o edítala a mano.',
            collect($p->json('piezas'))->firstWhere('estado', 'error')['error']);
        $this->assertSame(['ok', 'estrategia_estado', 'estrategia_error', 'generacion', 'piezas', 'videos'], array_keys($p->json()));
    }

    public function test_reglas_409_y_422_de_generar(): void
    {
        $this->falsearIA();
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['facebook']]);
        $url = "/api/marketing/campanas-360/{$c->id}/generar";

        $this->actingAs($admin)->postJson($url, ['alcance' => 'piezas'])->assertStatus(422)->assertJsonPath('error', 'sin_estrategia');
        $this->actingAs($admin)->postJson($url, ['alcance' => 'otro'])->assertStatus(422);

        $c->forceFill(['estrategia_estado' => 'generando'])->save();
        $this->actingAs($admin)->postJson($url, ['alcance' => 'todo'])->assertStatus(409)->assertJsonPath('error', 'generando')
            ->assertJsonPath('mensaje', 'Ya se está generando esta campaña.');

        $c->forceFill(['estrategia_estado' => 'lista', 'estrategia' => $this->estrategiaValida(['facebook'])])->save();
        $this->crearPieza($c, 'copy_facebook', 'A', ['texto' => 'x', 'hashtags' => [], 'cta' => '']);
        $this->actingAs($admin)->postJson($url, ['alcance' => 'piezas'])->assertStatus(409)->assertJsonPath('error', 'piezas_existentes');

        $c->forceFill(['status' => 'canceled'])->save();
        $this->actingAs($admin)->postJson($url, ['alcance' => 'estrategia'])->assertStatus(409)->assertJsonPath('error', 'campana_cerrada');
        $this->assertSame([], $this->iaFalsa->llamadas);
    }

    public function test_publico_solo_con_tipo_genera_y_el_prompt_no_menciona_lo_vacio(): void
    {
        $this->falsearIA([$this->estrategiaValida(['reels']), $this->respuestaPiezas('reel_guion', ['A']), $this->respuestaPiezas('reel_guion', ['B'])]);
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['reels'], 'publico' => ['descripcion' => 'Nuevos clientes',
            'zonas' => [], 'edad_min' => null, 'edad_max' => null, 'nichos' => [], 'intereses' => []]]);

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/generar", ['alcance' => 'todo'])->assertStatus(202);
        $this->assertSame('lista', $c->fresh()->estrategia_estado);

        [$estrategia, $guion] = array_column($this->iaFalsa->llamadas, 'mensaje');
        foreach ([$estrategia, $guion] as $m) {
            $this->assertStringContainsString('Nuevos clientes', $m);
            foreach (['"zonas"', '"edad_min"', '"edad_max"', '"intereses"', 'NICHOS DEL PÚBLICO', 'motivadores', 'dolores', 'vocabulario', '": null', 'sin dato'] as $nada) {
                $this->assertStringNotContainsString($nada, $m, "el prompt no debe mencionar {$nada}");
            }
        }
        $this->assertStringNotContainsString('CLIPS DE FONDO', $estrategia);
        $this->assertStringNotContainsString('Home office', $estrategia, 'los nichos no son el público');
        $this->assertStringContainsString('CATEGORÍAS DE CLIPS DE FONDO DISPONIBLES (no son el público', $guion);
        $this->assertStringContainsString('"slug": "familia"', $guion);
    }

    public function test_con_datos_de_publico_el_prompt_si_los_lleva(): void
    {
        $this->falsearIA([$this->estrategiaValida(['facebook'])]);
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['facebook']]);

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/generar", ['alcance' => 'estrategia'])->assertStatus(202);

        $m = $this->iaFalsa->llamadas[0]['mensaje'];
        $this->assertStringContainsString('"zonas"', $m);
        $this->assertStringContainsString('"edad_min": 25', $m);
        // Aunque la campaña traiga nichos, no se mandan como público ni con sus motivadores.
        $this->assertStringNotContainsString('NICHOS DEL PÚBLICO', $m);
        $this->assertStringNotContainsString('motivadores', $m);
    }

    public function test_un_reel_por_llamada_con_su_propio_reintento(): void
    {
        $malo = $this->respuestaPiezas('reel_guion', ['A']);
        $malo['piezas'][0]['contenido']['escenas'][0]['duracion_s'] = 40;
        $this->falsearIA([$this->estrategiaValida(['reels']), $malo, $this->respuestaPiezas('reel_guion', ['A']), $this->respuestaPiezas('reel_guion', ['B'])]);
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['reels']]);

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/generar", ['alcance' => 'todo'])->assertStatus(202);

        $guiones = array_slice(array_column($this->iaFalsa->llamadas, 'mensaje'), 1);
        $this->assertCount(3, $guiones, 'reel A + su reintento + reel B');
        $this->assertStringContainsString('en las variantes A (', $guiones[0]);
        $this->assertStringContainsString('Tu respuesta no cumplió', $guiones[1]);
        $this->assertStringContainsString('en las variantes B (', $guiones[2]);
        $this->assertSame(2, GeneratedContent::query()->where('source_campaign_id', $c->id)->where('pieza', 'reel_guion')->where('status', 'ready')->count());
    }

    public function test_motivo_del_rechazo_visible_en_espanol_y_registrado(): void
    {
        $largo = $this->respuestaPiezas('reel_guion', ['A']);
        foreach ($largo['piezas'][0]['contenido']['escenas'] as $i => $e) {
            $largo['piezas'][0]['contenido']['escenas'][$i]['duracion_s'] = 15;
        }
        $largo['piezas'][0]['contenido']['escenas'][] = $largo['piezas'][0]['contenido']['escenas'][2];
        $largo['piezas'][0]['contenido']['escenas'][3]['duracion_s'] = 7;
        $this->falsearIA([$this->estrategiaValida(['reels']), $largo, $largo, ['__cortada' => '{"piezas":[{"variante":"B","contenido":{"titulo":"Cor'], ['__cortada' => '{"piezas":[']]);
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['reels']]);

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/generar", ['alcance' => 'todo'])->assertStatus(202);

        $ficha = $this->actingAs($admin)->getJson("/api/marketing/campanas-360/{$c->id}")->assertOk();
        $this->assertSame('La IA no entregó un guion válido: el guion debe durar de 10 a 45 segundos (suma 52 s). Regenéralo o edítalo a mano.',
            $ficha->json('data.piezas.reel_guion.0.error'));
        $this->assertSame('La IA no entregó un guion válido: la respuesta llegó cortada (demasiado larga). Regenéralo o edítalo a mano.',
            $ficha->json('data.piezas.reel_guion.1.error'));

        $a = GeneratedContent::query()->where('source_campaign_id', $c->id)->where('variante', 'A')->first();
        $errores = $a->generation_metadata['errores_validacion'];
        $this->assertSame(['duracion_total_s' => 'El guion debe durar de 10 a 45 segundos (suma 52 s).'],
            array_map(fn ($m) => $m, array_combine(array_map(fn ($k) => preg_replace('/^variante A\./', '', $k), array_keys($errores['intento_1']['errores'])), $errores['intento_1']['errores'])));
        $this->assertArrayHasKey('intento_2', $errores);
        $this->assertFalse($errores['intento_1']['respuesta_cortada']);

        $b = GeneratedContent::query()->where('source_campaign_id', $c->id)->where('variante', 'B')->first();
        $this->assertTrue($b->generation_metadata['errores_validacion']['intento_1']['respuesta_cortada']);
        $this->assertTrue($b->generation_metadata['errores_validacion']['intento_2']['respuesta_cortada']);
        $this->assertStringContainsString('se CORTÓ por largo', $this->iaFalsa->llamadas[4]['mensaje'], 'el reintento pide responder más breve');
        $this->assertStringNotContainsString('Cor', json_encode($b->generation_metadata['errores_validacion']), 'no se guarda el texto de la IA');
    }

    public function test_generar_exige_alcance_explicito_h5(): void
    {
        $this->falsearIA();
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['facebook']]);
        $url = "/api/marketing/campanas-360/{$c->id}/generar";

        $this->actingAs($admin)->postJson($url)->assertStatus(422)->assertJsonValidationErrors(['alcance']);
        $this->actingAs($admin)->postJson($url, ['reemplazar' => true])->assertStatus(422)->assertJsonValidationErrors(['alcance']);
        $this->assertSame([], $this->iaFalsa->llamadas);
        $this->assertSame('sin_generar', $c->fresh()->estrategia_estado);
    }

    public function test_borrador_incompleto_no_genera_f1(): void
    {
        $this->falsearIA();
        $admin = $this->admin();
        $id = $this->actingAs($admin)->postJson('/api/marketing/campanas-360', ['nombre' => 'Solo paso 1', 'objetivo' => 'promocion'])
            ->assertStatus(201)->json('data.id');

        $r = $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$id}/generar", ['alcance' => 'todo'])
            ->assertStatus(422)->assertJsonPath('error', 'campana_incompleta');
        $this->assertSame(['publico.descripcion', 'oferta.descripcion', 'canales', 'presupuesto_mxn', 'fecha_inicio', 'fecha_fin'], $r->json('faltantes'));
        $this->assertStringStartsWith('Completa la campaña antes de seguir. Falta: la descripción del público', $r->json('mensaje'));
        $this->assertSame([], $this->iaFalsa->llamadas);
    }

    public function test_reemplazar_borra_las_piezas_de_texto_y_conserva_videos(): void
    {
        $this->falsearIA([$this->respuestaPiezas('copy_facebook', ['A', 'B'])]);
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['facebook']]);
        $c->forceFill(['estrategia_estado' => 'lista', 'estrategia' => $this->estrategiaValida(['facebook'])])->save();
        $vieja = $this->crearPieza($c, 'copy_facebook', 'A', ['texto' => 'vieja', 'hashtags' => [], 'cta' => '']);
        $video = $this->crearPieza($c, 'reel', 'A', [], 'ready');

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/generar", ['alcance' => 'piezas', 'reemplazar' => true])
            ->assertStatus(202);

        $this->assertSoftDeleted('marketing_generated_content', ['id' => $vieja->id]);
        $this->assertNotSoftDeleted('marketing_generated_content', ['id' => $video->id]);
        $this->assertSame(2, GeneratedContent::query()->where('source_campaign_id', $c->id)->where('pieza', 'copy_facebook')->where('status', 'ready')->count());
    }

    public function test_trabajo_de_estrategia_no_corre_si_la_campana_ya_no_esta_generando(): void
    {
        $this->falsearIA([$this->estrategiaValida(['facebook'])]);
        $c = $this->crearCampana360($this->admin(), ['canales' => ['facebook']]);

        app()->call([new GenerarEstrategiaCampanaJob($c->id), 'handle']);

        $this->assertSame([], $this->iaFalsa->llamadas);
    }

    public function test_editar_estrategia(): void
    {
        $this->falsearIA();
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['facebook', 'reels'], 'presupuesto_mxn' => '1000']);
        $url = "/api/marketing/campanas-360/{$c->id}/estrategia";

        $e = $this->estrategiaValida(['facebook', 'reels', 'tiktok']);
        $e['reparto_presupuesto'] = [['canal' => 'facebook', 'porcentaje' => '70'], ['canal' => 'reels', 'porcentaje' => '20']];
        $r = $this->actingAs($admin)->putJson($url, ['estrategia' => $e])->assertOk()->assertJsonPath('data.estrategia_estado', 'lista');
        $this->assertSame(['facebook', 'reels'], collect($r->json('data.estrategia.por_canal'))->pluck('canal')->all());
        $this->assertEquals([['canal' => 'facebook', 'porcentaje' => '77.78', 'monto_mxn' => '777.80'], ['canal' => 'reels', 'porcentaje' => '22.22', 'monto_mxn' => '222.20']],
            $r->json('data.estrategia.reparto_presupuesto'));

        $e['resumen'] = str_repeat('a', 601);
        $this->actingAs($admin)->putJson($url, ['estrategia' => $e])->assertStatus(422)->assertJsonValidationErrors(['resumen']);
        $this->actingAs($admin)->putJson($url, ['estrategia' => 'texto'])->assertStatus(422);

        $c->forceFill(['estrategia_estado' => 'generando'])->save();
        $this->actingAs($admin)->putJson($url, ['estrategia' => $this->estrategiaValida(['facebook'])])->assertStatus(409);
    }

    public function test_editar_pieza_valida_versiona_y_marca_editada(): void
    {
        $this->falsearIA();
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $p = $this->crearPieza($c, 'reel_guion', 'A', $this->guionValido());
        $url = "/api/marketing/campanas-360/{$c->id}/piezas/{$p->id}";

        $nuevo = $this->guionValido();
        $nuevo['escenas'][1]['duracion_s'] = 8;
        $r = $this->actingAs($admin)->putJson($url, ['contenido' => $nuevo])->assertOk();
        $r->assertJsonPath('data.editada', true)->assertJsonPath('data.contenido.duracion_total_s', 19)->assertJsonPath('data.estado', 'lista');
        $this->assertCount(1, $p->fresh()->generation_metadata['versiones']);

        $malo = $this->guionValido();
        $malo['escenas'][0]['texto_pantalla'] = 'Promo 🔥';
        $this->actingAs($admin)->putJson($url, ['contenido' => $malo])->assertStatus(422)->assertJsonValidationErrors(['escenas.0.texto_pantalla']);
        $this->assertSame(19, $p->fresh()->contenido['duracion_total_s'], 'una edición inválida no toca nada');

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($admin)->putJson($url, ['contenido' => $this->guionValido()])->assertOk();
        }
        $this->assertCount(5, $p->fresh()->generation_metadata['versiones']);

        $p->forceFill(['status' => 'generating'])->save();
        $this->actingAs($admin)->putJson($url, ['contenido' => $this->guionValido()])->assertStatus(409);

        $video = $this->crearPieza($c, 'reel', 'A', []);
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$c->id}/piezas/{$video->id}", ['contenido' => []])
            ->assertStatus(422)->assertJsonPath('error', 'no_editable');
    }

    public function test_regenerar_cambia_solo_esa_pieza(): void
    {
        $nueva = $this->respuestaPiezas('copy_facebook', ['B']);
        $nueva['piezas'][0]['contenido']['texto'] = 'Regenerada B';
        $this->falsearIA([$nueva]);
        $admin = $this->admin();
        $c = $this->crearCampana360($admin, ['canales' => ['facebook']]);
        $c->forceFill(['estrategia_estado' => 'lista', 'estrategia' => $this->estrategiaValida(['facebook'])])->save();
        $a = $this->crearPieza($c, 'copy_facebook', 'A', ['texto' => 'Original A', 'hashtags' => [], 'cta' => '']);
        $b = $this->crearPieza($c, 'copy_facebook', 'B', ['texto' => 'Original B', 'hashtags' => [], 'cta' => '']);
        $huellaA = sha1(json_encode($a->fresh()->only(['contenido', 'status', 'output_text', 'regeneraciones'])));

        $r = $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/piezas/{$b->id}/regenerar",
            ['indicaciones' => '<i>Más corto</i> y con humor'])->assertStatus(202);
        $r->assertJsonPath('data.estado', 'generando')->assertJsonPath('data.regeneraciones', 1);

        $this->assertSame($huellaA, sha1(json_encode($a->fresh()->only(['contenido', 'status', 'output_text', 'regeneraciones']))));
        $b->refresh();
        $this->assertSame('Regenerada B', $b->contenido['texto']);
        $this->assertSame('ready', $b->status);
        $this->assertSame('Original B', $b->generation_metadata['versiones'][0]['contenido']['texto']);
        $this->assertCount(1, $this->iaFalsa->llamadas);
        $this->assertStringContainsString('Más corto y con humor', $this->iaFalsa->llamadas[0]['mensaje']);
        $this->assertStringNotContainsString('<i>', $this->iaFalsa->llamadas[0]['mensaje']);
        $this->assertStringContainsString('Original A', $this->iaFalsa->llamadas[0]['mensaje'], 'las otras variantes van de contexto');
    }

    public function test_regenerar_409_422_y_404(): void
    {
        $this->falsearIA([], [ClienteIACampana::PIEZAS => false]);
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $p = $this->crearPieza($c, 'copy_facebook', 'A', ['texto' => 'x', 'hashtags' => [], 'cta' => ''], 'generating');
        $base = "/api/marketing/campanas-360/{$c->id}/piezas";

        $this->actingAs($admin)->postJson("{$base}/{$p->id}/regenerar")->assertStatus(422)->assertJsonPath('error', 'ia_no_configurada');

        // El controlador queda en caché en la ruta: se cambia el estado del mismo doble, no se crea otro.
        $this->iaFalsa->configurada[ClienteIACampana::PIEZAS] = true;
        $this->actingAs($admin)->postJson("{$base}/{$p->id}/regenerar")->assertStatus(409)->assertJsonPath('error', 'generando');
        $this->actingAs($admin)->postJson("{$base}/{$p->id}/regenerar", ['indicaciones' => 'usa {{x}}'])->assertStatus(422);

        $otra = $this->crearCampana360($admin);
        $ajena = $this->crearPieza($otra, 'copy_facebook', 'A', ['texto' => 'x', 'hashtags' => [], 'cta' => '']);
        $this->actingAs($admin)->postJson("{$base}/{$ajena->id}/regenerar")->assertStatus(404);
        $this->actingAs($admin)->putJson("{$base}/{$ajena->id}", ['contenido' => []])->assertStatus(404);
        $this->assertSame([], $this->iaFalsa->llamadas);
    }
}
