<?php

namespace Tests\Feature\Marketing\Fase2\Qa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase2\Qa\Concerns\EscenarioFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 2 — «Producir en Video MVM» (contrato §2.4 rutas 12–14, §2.6; criterios 5 y 6).
 * Queue::fake: RenderVideoJob en `video-render` con `_escenas_mvm`, `voiceover_text`, `_voz`, `_musica` exactos;
 * fila con pieza, pieza_origen_id, source_campaign_id y plantilla correcta; doble POST → una fila + 409;
 * pieza no guion → 422; guion inválido → 422 con errores; ver/miniatura 404 hasta listo; ruta manipulada → 404.
 */
class ProducirVideoTest extends MarketingTestCase
{
    use EscenarioFase2;

    private int $campana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioFase2();
        $this->campana = $this->campanaGenerada(['canales' => ['reels', 'video', 'facebook']]);
    }

    protected function tearDown(): void
    {
        $this->limpiarArchivosQa();
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function producir(int $pieza, array $cuerpo = []): \Illuminate\Testing\TestResponse
    {
        return $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$this->campana}/piezas/{$pieza}/producir", $cuerpo);
    }

    private function jobRender(): object
    {
        $jobs = Queue::pushed(self::JOB_RENDER);
        $this->assertCount(1, $jobs, 'Un solo RenderVideoJob');

        return $jobs->first();
    }

    public function test_producir_reel_crea_una_fila_y_despacha_el_render_con_variables_exactas(): void
    {
        $guion = $this->piezaId($this->campana, 'reel_guion');
        $res = $this->producir($guion);
        $res->assertStatus(202)->assertJsonPath('ok', true)->assertJsonPath('data.estado', 'en_cola')
            ->assertJsonPath('data.estado_etiqueta', 'En cola')->assertJsonPath('data.pieza', 'reel')
            ->assertJsonPath('data.guion_id', $guion)->assertJsonPath('data.formato', '9:16')
            ->assertJsonPath('data.url_ver', null)->assertJsonPath('data.url_miniatura', null);

        $v = DB::table('marketing_generated_content')->find($res->json('data.id'));
        $plantilla = DB::table('marketing_video_templates')->where('company_id', 1)->where('slug', 'guion-escenas-9-16')->first();
        $this->assertSame('video', $v->type);
        $this->assertSame('reel', $v->pieza);
        $this->assertSame($guion, (int) $v->pieza_origen_id);
        $this->assertSame($this->campana, (int) $v->source_campaign_id);
        $this->assertSame((int) $plantilla->id, (int) $v->template_id);
        $this->assertSame('App\\Models\\Marketing\\VideoTemplate', $v->template_type);
        $this->assertSame('pending', $v->status);
        $this->assertSame('queued', $v->render_stage);
        $this->assertSame(0, (int) $v->render_progress);
        $this->assertSame('mvm_ffmpeg', $v->generation_engine);
        $this->assertSame(1, (int) $v->company_id);
        $meta = json_decode($v->generation_metadata, true);
        $this->assertSame('guion-escenas-9-16', $meta['template_slug']);
        $this->assertSame('9:16', $meta['aspect_ratio']);
        $this->assertSame(40, strlen($meta['guion_hash']));

        $job = $this->jobRender();
        $this->assertSame('video-render', $job->queue);
        $this->assertSame((int) $v->id, $job->generatedContentId);
        $this->assertSame((int) $plantilla->id, $job->templateId);
        $this->assertSame(['aspect_ratio' => '9:16'], $job->options);
        $this->assertSame(1, $job->companyId);

        $c = $this->contenidoValido('reel_guion');
        $vars = $job->variables;
        $this->assertSame('nova', $vars['_voz'], 'Sin cuerpo: la voz del guion');
        $this->assertSame('uplifting_family', $vars['_musica'], 'Sin cuerpo: la música del guion');
        $this->assertSame('familia', $vars['_niche_slug'], 'Primera categoria_broll');
        $this->assertSame('fade', $vars['_transition_type']);
        $this->assertEquals(0.3, $vars['_transition_duration']);
        $this->assertSame(implode(' ', array_filter(array_column($c['escenas'], 'voz_en_off'), fn ($t) => $t !== '')), $vars['voiceover_text']);
        $this->assertSame($this->normalizarEscenas([
            ['type' => 'broll_overlay', 'duration' => 4, 'broll_source' => 'niche:familia', 'bg_darken' => 0.35,
                'layers' => [['type' => 'kinetic_text', 'text' => '¿Se traba tu serie?', 'duration_sec' => 4, 'style' => 'huge_centered', 'position' => 'center']]],
            ['type' => 'feature_highlight', 'duration' => 4, 'main_text' => '$599 al mes', 'sub_text' => ''],
            ['type' => 'call_to_action', 'duration' => 4, 'cta_text' => 'Contrata hoy', 'phone' => '{{brand.phone}}'],
        ]), $this->normalizarEscenas($vars['_escenas_mvm']), 'GuionAMvm según la tabla de §2.6');
        $this->assertSame($this->normalizarEscenas($vars), $this->normalizarEscenas(json_decode($v->input_variables, true)), 'input_variables = variables del job (MySQL JSON reordena claves)');
        $this->assertStringNotContainsString('color', json_encode($vars['_escenas_mvm']), 'Ningún color viene del guion');
    }

    /** Ordena claves (recursivo) y castea números enteros a int, para comparar sin depender del orden ni de int/float. */
    private function normalizarEscenas(array $v): array
    {
        $n = function ($x) use (&$n) {
            if (is_array($x)) {
                $x = array_map($n, $x);
                if (array_keys($x) !== range(0, count($x) - 1)) {
                    ksort($x);
                }

                return $x;
            }

            return (is_float($x) && floor($x) == $x) ? (int) $x : $x;
        };

        return $n($v);
    }

    public function test_producir_video_16_9_con_voz_y_musica_elegidas(): void
    {
        $guion = $this->piezaId($this->campana, 'video_guion');
        $res = $this->producir($guion, ['voz' => 'shimmer', 'musica' => 'sin_musica']);
        $res->assertStatus(202)->assertJsonPath('data.pieza', 'video')->assertJsonPath('data.formato', '16:9');
        $job = $this->jobRender();
        $this->assertSame(['aspect_ratio' => '16:9'], $job->options);
        $this->assertSame('shimmer', $job->variables['_voz']);
        $this->assertNull($job->variables['_musica'], 'sin_musica → null');
        $this->assertSame(
            (int) DB::table('marketing_video_templates')->where('company_id', 1)->where('slug', 'guion-escenas-16-9')->value('id'),
            $job->templateId
        );
        // logo → logo_reveal
        $this->assertSame('logo_reveal', $job->variables['_escenas_mvm'][0]['type']);
        $this->assertSame(3, (int) $job->variables['_escenas_mvm'][0]['duration']);
    }

    public function test_doble_clic_en_producir_una_fila_un_trabajo_y_409(): void
    {
        $guion = $this->piezaId($this->campana, 'reel_guion');
        $antes = $this->contarFilas('marketing_generated_content', ['type' => 'video']);
        $this->producir($guion)->assertStatus(202);
        $this->producir($guion)->assertStatus(409)->assertJson(['ok' => false, 'error' => 'produccion_en_curso', 'mensaje' => 'Ese guion ya se está produciendo.']);
        DB::table('marketing_generated_content')->where('pieza_origen_id', $guion)->update(['status' => 'generating']);
        $this->producir($guion)->assertStatus(409);
        $this->assertSame($antes + 1, $this->contarFilas('marketing_generated_content', ['type' => 'video']));
        Queue::assertPushed(self::JOB_RENDER, 1);
    }

    public function test_tras_listo_o_error_se_puede_volver_a_producir(): void
    {
        $guion = $this->piezaId($this->campana, 'reel_guion');
        $this->producir($guion)->assertStatus(202);
        DB::table('marketing_generated_content')->where('pieza_origen_id', $guion)->update(['status' => 'failed', 'failure_reason' => 'QA']);
        $this->producir($guion)->assertStatus(202);
        Queue::assertPushed(self::JOB_RENDER, 2);
    }

    public function test_pieza_que_no_es_guion_o_no_lista_da_422(): void
    {
        $this->producir($this->piezaId($this->campana, 'copy_facebook'))->assertStatus(422)->assertJsonPath('error', 'no_es_guion');
        $guion = $this->piezaId($this->campana, 'reel_guion', 'B');
        foreach (['pending', 'generating', 'failed'] as $s) {
            DB::table('marketing_generated_content')->where('id', $guion)->update(['status' => $s]);
            $this->producir($guion)->assertStatus(422)->assertJsonPath('error', 'pieza_no_lista');
        }
        Queue::assertNotPushed(self::JOB_RENDER);
    }

    public function test_guion_invalido_en_bd_da_422_guion_invalido_con_errores(): void
    {
        $guion = $this->piezaId($this->campana, 'reel_guion');
        [, $malo] = $this->casoInvalido('escena_40_s');
        DB::table('marketing_generated_content')->where('id', $guion)->update(['contenido' => json_encode($malo)]);
        $res = $this->producir($guion);
        $res->assertStatus(422)->assertJsonPath('error', 'guion_invalido');
        $this->assertNotEmpty($res->json('errores'), 'Errores por escena');
        Queue::assertNotPushed(self::JOB_RENDER);
    }

    public function test_guion_manipulado_en_bd_con_color_o_llaves_no_llega_al_motor(): void
    {
        $guion = $this->piezaId($this->campana, 'reel_guion');
        $c = $this->contenidoValido('reel_guion');
        $c['escenas'][1]['visual']['color'] = "red'@0.5:enable=1";
        $c['escenas'][1]['texto_pantalla'] = '{{_override_primary_color}}';
        DB::table('marketing_generated_content')->where('id', $guion)->update(['contenido' => json_encode($c)]);
        $res = $this->producir($guion);
        if ($res->getStatusCode() === 202) {
            $vars = json_encode($this->jobRender()->variables);
            $this->assertStringNotContainsString('red', $vars);
            $this->assertStringNotContainsString('_override_primary_color', $vars);
        } else {
            $res->assertStatus(422);
        }
    }

    public function test_voz_y_musica_fuera_de_catalogo_dan_422(): void
    {
        $guion = $this->piezaId($this->campana, 'reel_guion');
        foreach ([['voz' => 'darth'], ['musica' => 'reggaeton'], ['voz' => ['nova']], ['musica' => '../../etc/passwd'], ['voz' => "nova' ; rm -rf /"]] as $cuerpo) {
            $res = $this->producir($guion, $cuerpo);
            $this->assertSame(422, $res->getStatusCode(), json_encode($cuerpo) . ' → ' . $this->etiqueta($res));
        }
        Queue::assertNotPushed(self::JOB_RENDER);
    }

    public function test_plantilla_inactiva_da_422_plantilla_no_disponible(): void
    {
        DB::table('marketing_video_templates')->where('company_id', 1)->where('slug', 'guion-escenas-9-16')->update(['active' => 0]);
        $this->producir($this->piezaId($this->campana, 'reel_guion'))->assertStatus(422)->assertJsonPath('error', 'plantilla_no_disponible');
        Queue::assertNotPushed(self::JOB_RENDER);
    }

    public function test_video_aparece_en_la_ficha_el_progreso_y_la_cola_existente(): void
    {
        $guion = $this->piezaId($this->campana, 'reel_guion');
        $vid = $this->producir($guion)->assertStatus(202)->json('data.id');
        $p = $this->como($this->admin)->getJson("/api/marketing/campanas-360/{$this->campana}/progreso")->assertStatus(200);
        $v = collect($p->json('videos'))->firstWhere('id', $vid);
        $this->assertNotNull($v);
        foreach (['id', 'pieza', 'guion_id', 'formato', 'estado', 'estado_etiqueta', 'error', 'sin_voz', 'duracion_s', 'url_ver', 'url_miniatura', 'en_cola_desde', 'demorado', 'creado_at'] as $k) {
            $this->assertArrayHasKey($k, $v, "Video sin {$k}");
        }
        $this->assertFalse($v['demorado']);
        $ficha = $this->como($this->admin)->getJson("/api/marketing/campanas-360/{$this->campana}")->assertStatus(200);
        $this->assertContains($vid, $ficha->json('data.piezas.reel_guion.0.video_ids'));
        $this->assertSame(1, $ficha->json('data.videos_resumen.en_proceso'));

        // Video → Cola (pantalla existente) lo lista
        $cola = $this->como($this->admin)->getJson('/api/marketing/generated-content?type=video&per_page=100');
        $this->assertSame(200, $cola->getStatusCode(), $this->etiqueta($cola));
        $this->assertStringContainsString('"id":' . $vid, (string) $cola->getContent(), 'El video aparece también en Video → Cola');
    }

    public function test_estados_del_video_y_demorado(): void
    {
        $guion = $this->piezaId($this->campana, 'reel_guion');
        $vid = $this->producir($guion)->json('data.id');
        $ver = function () use ($vid) {
            return collect($this->como($this->admin)->getJson("/api/marketing/campanas-360/{$this->campana}/progreso")->json('videos'))->firstWhere('id', $vid);
        };
        DB::table('marketing_generated_content')->where('id', $vid)->update(['created_at' => now()->subMinutes(11), 'updated_at' => now()->subMinutes(11)]);
        $this->assertTrue($ver()['demorado'], 'En cola > 10 min → demorado');
        DB::table('marketing_generated_content')->where('id', $vid)->update(['status' => 'generating', 'updated_at' => now()->subMinutes(5)]);
        $this->assertSame('produciendo', $ver()['estado']);
        $this->assertSame('Produciendo', $ver()['estado_etiqueta']);
        $this->assertFalse($ver()['demorado']);
        DB::table('marketing_generated_content')->where('id', $vid)->update(['updated_at' => now()->subMinutes(16)]);
        $this->assertTrue($ver()['demorado'], 'Produciendo sin cambio > 15 min → demorado');
        DB::table('marketing_generated_content')->where('id', $vid)->update(['status' => 'failed', 'failure_reason' => "RuntimeException: ffmpeg exited 1 in /var/www/x.php:12\n#0 trace"]);
        $e = $ver();
        $this->assertSame('error', $e['estado']);
        $this->assertSame('Error', $e['estado_etiqueta']);
        $this->assertNotEmpty($e['error']);
        $this->assertDoesNotMatchRegularExpression('/\.php|#0|Exception/', $e['error'], 'Mensaje corto, nunca la traza');
        DB::table('marketing_generated_content')->where('id', $vid)->update(['status' => 'ready', 'generation_metadata' => json_encode(['voz_generada' => false])]);
        $e = $ver();
        $this->assertSame('listo', $e['estado']);
        $this->assertTrue($e['sin_voz']);
    }

    public function test_ver_y_miniatura_404_hasta_listo_y_luego_200_con_tipo_correcto(): void
    {
        $guion = $this->piezaId($this->campana, 'reel_guion');
        $vid = $this->producir($guion)->json('data.id');
        $base = "/api/marketing/campanas-360/{$this->campana}/videos/{$vid}";
        $this->como($this->admin)->get("{$base}/ver")->assertStatus(404);
        $this->como($this->admin)->get("{$base}/miniatura")->assertStatus(404);

        $dir = storage_path('app/marketing/videos/1/qa2');
        @mkdir($dir, 0775, true);
        $mp4 = $dir . '/ver-' . uniqid() . '.mp4';
        $jpg = preg_replace('/\.mp4$/', '_thumb.jpg', $mp4);
        exec('ffmpeg -nostdin -loglevel error -y -f lavfi -i color=c=red:s=64x64:d=1 -pix_fmt yuv420p ' . escapeshellarg($mp4));
        exec('ffmpeg -nostdin -loglevel error -y -i ' . escapeshellarg($mp4) . ' -frames:v 1 ' . escapeshellarg($jpg));
        $this->archivosQa[] = $mp4;
        $this->archivosQa[] = $jpg;
        DB::table('marketing_generated_content')->where('id', $vid)->update([
            'status' => 'ready', 'output_path' => 'marketing/videos/1/qa2/' . basename($mp4), 'thumbnail_path' => 'marketing/videos/1/qa2/' . basename($jpg),
        ]);
        $r = $this->como($this->admin)->get("{$base}/ver");
        $r->assertStatus(200);
        $this->assertStringStartsWith('video/mp4', (string) $r->headers->get('Content-Type'));
        $m = $this->como($this->admin)->get("{$base}/miniatura");
        $m->assertStatus(200);
        $this->assertStringStartsWith('image/jpeg', (string) $m->headers->get('Content-Type'));
        $rango = $this->como($this->admin)->get("{$base}/ver", ['Range' => 'bytes=0-99']);
        $this->assertContains($rango->getStatusCode(), [200, 206], 'Admite Range');

        $lista = collect($this->como($this->admin)->getJson("/api/marketing/campanas-360/{$this->campana}/progreso")->json('videos'))->firstWhere('id', $vid);
        $this->assertSame("{$base}/ver", $lista['url_ver']);
        $this->assertSame("{$base}/miniatura", $lista['url_miniatura']);
    }

    public function test_ruta_de_archivo_manipulada_da_404(): void
    {
        $guion = $this->piezaId($this->campana, 'reel_guion');
        $vid = $this->producir($guion)->json('data.id');
        $base = "/api/marketing/campanas-360/{$this->campana}/videos/{$vid}";
        foreach (['../../../.env', 'marketing/videos/../../../.env', '/etc/passwd', 'marketing/videos/1/no-existe.mp4', '../../database.sqlite'] as $ruta) {
            DB::table('marketing_generated_content')->where('id', $vid)->update(['status' => 'ready', 'output_path' => $ruta, 'thumbnail_path' => $ruta]);
            $r = $this->como($this->admin)->get("{$base}/ver");
            $this->assertSame(404, $r->getStatusCode(), "ver con {$ruta} → " . $r->getStatusCode());
            $this->assertStringNotContainsString('APP_KEY', (string) $r->getContent());
            $this->assertSame(404, $this->como($this->admin)->get("{$base}/miniatura")->getStatusCode(), "miniatura con {$ruta}");
        }
    }

    public function test_campana_borrada_o_cerrada_no_produce(): void
    {
        $guion = $this->piezaId($this->campana, 'reel_guion');
        DB::table('marketing_campaigns')->where('id', $this->campana)->update(['status' => 'canceled']);
        $res = $this->producir($guion);
        $this->assertContains($res->getStatusCode(), [202, 409, 422], $this->etiqueta($res));
        DB::table('marketing_campaigns')->where('id', $this->campana)->update(['deleted_at' => now()]);
        $this->producir($guion)->assertStatus(404);
    }
}
