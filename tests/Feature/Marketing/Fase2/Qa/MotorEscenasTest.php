<?php

namespace Tests\Feature\Marketing\Fase2\Qa;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\VideoTemplate;
use App\Modules\Addons\Marketing\Jobs\RenderVideoJob;
use App\Modules\Addons\Marketing\Services\Tts\TtsService;
use App\Modules\Addons\Marketing\Services\Video\FFmpegService;
use App\Modules\Addons\Marketing\Services\Video\VideoTemplateRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Marketing\Fase2\Qa\Concerns\AssetsEspia;
use Tests\Feature\Marketing\Fase2\Qa\Concerns\EscenarioFase2;
use Tests\Feature\Marketing\Fase2\Qa\Concerns\RendererEspia;
use Tests\Feature\Marketing\Fase2\Qa\Concerns\TtsEspia;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 2 — cambios al motor de Video MVM (contrato §2.6, F2-1, F2-7; criterio 12; riesgos R5 y R7).
 *
 * 1. SIN la bandera `escenas_desde_variables`, las plantillas existentes (genérica 9:16 y 16:9, y la de nicho
 *    `multivariant-plan-promo-gamer` con brief) piden EXACTAMENTE las mismas escenas y el mismo TTS que con el código
 *    de la base (08d58e7e3) — `fixtures/escenas-base.json`, capturado antes de que backend tocara el motor.
 * 2. CON la bandera: escenas desde `_escenas_mvm` con lista blanca (tipos, claves, sin `*color*`, duración 2–15,
 *    máx. 8, `broll_source` solo `niche:<slug>`), vacías → error, `_voz` antes que la del nicho, `_musica` con
 *    `type = music`.
 * 3. RenderVideoJob: idempotente si ya está `ready`, guarda `render_log`, `voz_generada`, `tts_cost_usd` y
 *    `failure_reason`; firma, cola, tries y timeout sin cambio.
 */
class MotorEscenasTest extends MarketingTestCase
{
    use EscenarioFase2;

    /** @var array<string,int> slug => id de las plantillas de la base sembradas en la transacción */
    private array $base = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fotografiarArchivosMotor();
        $this->contexto()->limpiar();
    }

    protected function tearDown(): void
    {
        $this->limpiarArchivosQa();
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function sembrarPlantillasBase(): void
    {
        // La base se capturó sin Brand Kit: se quita dentro de la transacción (por si la vista previa sembró uno).
        DB::table('marketing_settings')->where('company_id', 1)->where('key', 'like', 'brand\\_%')->delete();
        foreach (json_decode(file_get_contents(__DIR__ . '/fixtures/plantillas-base.json'), true) as $row) {
            DB::table('marketing_video_templates')->where('company_id', 1)->where('slug', $row['slug'])->delete();
            $row['created_at'] = now();
            $row['updated_at'] = now();
            $this->base[$row['slug']] = DB::table('marketing_video_templates')->insertGetId($row);
        }
        DB::table('marketing_niches')->updateOrInsert(['company_id' => 1, 'slug' => 'gamer'], [
            'name' => 'Gamer QA', 'description' => 'QA', 'motivators' => '[]', 'pain_points' => '[]', 'objections' => '[]', 'vocabulary' => '[]',
            'broll_tags' => '[]', 'emotional_tone' => 'energetic', 'voice_style' => 'energetic', 'music_mood' => 'epic_electronic',
            'preferred_voice_id' => 'onyx', 'active' => 1, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function plantillaGuion(string $slug = 'guion-escenas-9-16'): VideoTemplate
    {
        $t = VideoTemplate::query()->where('company_id', 1)->where('slug', $slug)->first();
        $this->assertNotNull($t, "La migración 2026_11_13_200200 debe crear {$slug}");

        return $t;
    }

    // ── 1. lo existente no cambia ────────────────────────────────────────────

    public function test_sin_bandera_las_plantillas_existentes_piden_las_mismas_escenas_y_tts_que_en_la_base(): void
    {
        $this->sembrarPlantillasBase();
        $esperado = json_decode(file_get_contents(__DIR__ . '/fixtures/escenas-base.json'), true);
        $casos = require __DIR__ . '/Concerns/casos-motor-base.php';
        $this->assertCount(4, $casos);
        foreach ($casos as $nombre => [$slug, $vars, $ar]) {
            $tts = new TtsEspia(1);
            $r = RendererEspia::crear($tts);
            $res = $r->render(VideoTemplate::find($this->base[$slug]), $vars, $ar, 1);
            // misma ida y vuelta por JSON que el fixture (3.0 → 3), para comparar valores y no tipos de PHP
            $rt = fn ($x) => json_decode(json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);
            $this->assertSame($esperado[$nombre]['escenas'], $rt($r->escenas), "{$nombre}: las escenas cambiaron respecto a la base");
            $this->assertSame($esperado[$nombre]['tts'], $rt($tts->llamadas), "{$nombre}: el TTS cambió (texto o voz) respecto a la base");
            $this->assertSame($esperado[$nombre]['error'], $res['error'] ?? null, "{$nombre}: el flujo terminó distinto");
        }
    }

    public function test_las_plantillas_de_guion_no_aparecen_como_plantillas_normales_de_otra_categoria(): void
    {
        foreach (['guion-escenas-9-16' => ['9:16', 30], 'guion-escenas-16-9' => ['16:9', 60]] as $slug => [$ar, $dur]) {
            $t = $this->plantillaGuion($slug);
            $this->assertSame('guion_escenas', $t->category);
            $this->assertSame(1, (int) $t->company_id);
            $this->assertTrue((bool) $t->active);
            $this->assertSame('1.1', (string) $t->engine_version);
            $this->assertSame($dur, (int) $t->duration_seconds);
            $schema = is_array($t->schema) ? $t->schema : json_decode($t->schema, true);
            $this->assertTrue($schema['escenas_desde_variables'] ?? null);
            $this->assertSame([], $schema['scenes']);
            $this->assertSame('{{voiceover_text}}', $schema['voiceover_script']);
            $this->assertSame([$ar], $schema['supported_aspect_ratios']);
        }
        $this->assertSame(2, DB::table('marketing_video_templates')->where('category', 'guion_escenas')->count());
    }

    // ── 2. con bandera ───────────────────────────────────────────────────────

    private function renderConEscenas(array $escenas, array $extra = [], string $slug = 'guion-escenas-9-16', ?AssetsEspia $assets = null): array
    {
        $tts = new TtsEspia(1);
        $r = RendererEspia::crear($tts, $assets);
        $res = $r->render($this->plantillaGuion($slug), array_merge([
            '_escenas_mvm' => $escenas, 'voiceover_text' => 'Hola QA', '_transition_type' => 'fade', '_transition_duration' => 0.3,
        ], $extra), str_contains($slug, '16-9') ? '16:9' : '9:16', 1);

        return [$r, $tts, $res];
    }

    public function test_con_bandera_las_escenas_salen_de_las_variables(): void
    {
        [$r] = $this->renderConEscenas([
            ['type' => 'logo_reveal', 'duration' => 3],
            ['type' => 'feature_highlight', 'duration' => 4, 'main_text' => 'Fibra', 'sub_text' => ''],
            ['type' => 'broll_overlay', 'duration' => 5, 'broll_source' => 'niche:familia', 'bg_darken' => 0.35,
                'layers' => [['type' => 'kinetic_text', 'text' => 'Hola', 'duration_sec' => 5, 'style' => 'huge_centered', 'position' => 'center']]],
            ['type' => 'call_to_action', 'duration' => 2, 'cta_text' => 'Llama', 'phone' => '{{brand.phone}}'],
        ]);
        $this->assertSame(['logo_reveal', 'feature_highlight', 'broll_overlay', 'call_to_action'], array_column($r->escenas, 'type'));
        $this->assertSame([3.0, 4.0, 5.0, 2.0], array_map('floatval', array_column($r->escenas, 'duration')), 'Cada escena con su duración');
        $this->assertSame('1080x1920', $r->escenas[0]['tamano']);
    }

    public function test_lista_blanca_quita_colores_tipos_y_fuentes_raras(): void
    {
        [$r] = $this->renderConEscenas([
            ['type' => 'feature_highlight', 'duration' => 4, 'main_text' => 'A', 'bg_color' => "red'@0.5", 'text_color' => 'black', 'fontcolor' => 'x'],
            ['type' => 'split_screen', 'duration' => 4, 'main_text' => 'tipo fuera de la lista'],
            ['type' => 'broll_overlay', 'duration' => 4, 'broll_source' => '/etc/passwd'],
            ['type' => 'broll_overlay', 'duration' => 4, 'broll_source' => 'niche:../../x'],
            ['type' => 'broll_overlay', 'duration' => 4, 'broll_source' => 'url:http://evil.invalid/a.mp4'],
            ['type' => 'call_to_action', 'duration' => 4, 'cta_text' => 'Llama', 'phone' => '{{brand.phone}}', 'cta_color' => '#000'],
        ]);
        $todo = json_encode($r->escenas);
        $this->assertDoesNotMatchRegularExpression('/"[a-z_]*color[a-z_]*"\s*:\s*"(red|black|x|#000)/i', $todo, 'Ningún color del guion llega a ffmpeg');
        $this->assertNotContains('split_screen', array_column($r->escenas, 'type'));
        $this->assertStringNotContainsString('/etc/passwd', $todo);
        $this->assertStringNotContainsString('../', $todo);
        $this->assertStringNotContainsString('evil.invalid', $todo);
    }

    public function test_duraciones_fuera_de_rango_y_mas_de_8_escenas(): void
    {
        $muchas = [];
        for ($i = 0; $i < 12; $i++) {
            $muchas[] = ['type' => 'feature_highlight', 'duration' => 3, 'main_text' => "E{$i}", 'sub_text' => ''];
        }
        [$r] = $this->renderConEscenas($muchas);
        $this->assertLessThanOrEqual(8, count($r->escenas), 'Máximo 8 escenas');

        [$r] = $this->renderConEscenas([
            ['type' => 'feature_highlight', 'duration' => 600, 'main_text' => 'larga'],
            ['type' => 'feature_highlight', 'duration' => 0, 'main_text' => 'cero'],
            ['type' => 'feature_highlight', 'duration' => -5, 'main_text' => 'negativa'],
            ['type' => 'call_to_action', 'duration' => 4, 'cta_text' => 'ok'],
        ]);
        foreach ($r->escenas as $e) {
            $this->assertGreaterThanOrEqual(2, $e['duration'], 'Duración mínima 2');
            $this->assertLessThanOrEqual(15, $e['duration'], 'Duración máxima 15');
        }
    }

    public function test_sin_escenas_o_escenas_no_arreglo_da_error_en_espanol(): void
    {
        foreach ([[], 'texto', null, [['type' => 'split_screen', 'duration' => 3]]] as $malas) {
            [$r, , $res] = $this->renderConEscenas(is_array($malas) ? $malas : [], is_array($malas) ? [] : ['_escenas_mvm' => $malas]);
            $this->assertFalse($res['success'], json_encode($malas));
            $this->assertSame([], $r->escenas);
            if ($malas === []) {
                $this->assertSame('El guion no trae escenas.', $res['error']);
            }
        }
    }

    public function test_voz_elegida_gana_a_la_del_nicho_y_voz_fuera_de_lista_se_ignora(): void
    {
        $this->nicho('familia', 'Familia QA', 'nova', 'uplifting_family');
        $escenas = [['type' => 'feature_highlight', 'duration' => 3, 'main_text' => 'A'], ['type' => 'call_to_action', 'duration' => 3, 'cta_text' => 'B']];
        [, $tts] = $this->renderConEscenas($escenas, ['_voz' => 'onyx', '_niche_slug' => 'familia']);
        $this->assertSame('onyx', $tts->llamadas[0]['opciones']['voice'] ?? null);
        $this->assertSame('Hola QA', $tts->llamadas[0]['texto']);
        [, $tts] = $this->renderConEscenas($escenas, ['_voz' => 'voz_inventada', '_niche_slug' => 'familia']);
        $this->assertSame('nova', $tts->llamadas[0]['opciones']['voice'] ?? null, 'Voz fuera de §2.5.6 → la del nicho');
    }

    public function test_con_bandera_y_render_real_la_musica_se_busca_como_music_y_la_duracion_es_la_suma(): void
    {
        $this->montarEscenarioFase2();
        $dir = storage_path('app/marketing/assets/qa2');
        @mkdir($dir, 0775, true);
        $mp3 = $dir . '/musica-' . uniqid() . '.mp3';
        exec('ffmpeg -nostdin -loglevel error -y -f lavfi -i sine=frequency=220:duration=10 -c:a libmp3lame -b:a 64k ' . escapeshellarg($mp3));
        $this->archivosQa[] = $mp3;
        DB::table('marketing_assets')->insert([
            'company_id' => 1, 'type' => 'music', 'category' => 'uplifting_family', 'name' => 'QA2 música', 'path' => 'marketing/assets/qa2/' . basename($mp3),
            'mime_type' => 'audio/mpeg', 'size_bytes' => filesize($mp3), 'license' => 'owned', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $assets = new AssetsEspia(1);
        $renderer = new VideoTemplateRenderer(new FFmpegService(1), new TtsService(1), $assets);
        $res = $renderer->render($this->plantillaGuion(), [
            '_escenas_mvm' => [
                ['type' => 'feature_highlight', 'duration' => 2, 'main_text' => 'Fibra', 'sub_text' => ''],
                ['type' => 'feature_highlight', 'duration' => 3, 'main_text' => 'QA', 'sub_text' => ''],
                ['type' => 'call_to_action', 'duration' => 3, 'cta_text' => 'Llama', 'phone' => '{{brand.phone}}'],
            ],
            'voiceover_text' => 'Prueba de voz', '_voz' => 'nova', '_musica' => 'uplifting_family',
            '_transition_type' => 'fade', '_transition_duration' => 0.3,
        ], '9:16', 1);
        if (! empty($res['output_path'])) {
            $this->archivosQa[] = storage_path('app/' . $res['output_path']);
            $this->archivosQa[] = storage_path('app/' . ($res['thumbnail_path'] ?? 'x'));
        }
        $this->assertTrue($res['success'], (string) ($res['error'] ?? '') . ' ' . json_encode($res['log'] ?? []));
        $this->assertContains(['type' => 'music', 'category' => 'uplifting_family'], $assets->pedidos, 'F2-7: la música se busca con type = music');
        $this->assertNotContains('audio', array_column($assets->pedidos, 'type'));
        $this->assertTrue($res['voz_generada'] ?? null, 'El resultado trae voz_generada');
        $this->assertArrayHasKey('cost_usd', $res);
        $info = (new FFmpegService(1))->probe(storage_path('app/' . $res['output_path']));
        $suma = 8.0;
        $this->assertEqualsWithDelta($suma, (float) $info['duration'], 2 * 0.3 + 1, 'Duración ≈ suma de escenas (no duration_seconds = 30)');
        $this->assertCount(1, $this->llamadasVoz);
        $this->assertSame('nova', $this->llamadasVoz[0]['voice']);
    }

    // ── 3. RenderVideoJob ────────────────────────────────────────────────────

    public function test_render_job_conserva_firma_cola_tries_y_timeout(): void
    {
        $j = new RenderVideoJob(1, 2, ['a' => 1], ['aspect_ratio' => '9:16'], 1);
        $this->assertSame(2, $j->tries);
        $this->assertSame(600, $j->timeout);
        $params = array_map(fn ($p) => $p->getName(), (new \ReflectionMethod(RenderVideoJob::class, '__construct'))->getParameters());
        $this->assertSame(['generatedContentId', 'templateId', 'variables', 'options', 'companyId'], $params);
        $mw = $j->middleware();
        $this->assertNotEmpty($mw, 'WithoutOverlapping por id');
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $mw[0]);
        $this->assertSame('mkt-render-1', $mw[0]->key);
        $this->assertSame(900, $mw[0]->expiresAfter);
    }

    private function filaVideo(array $attrs = []): GeneratedContent
    {
        $id = DB::table('marketing_generated_content')->insertGetId(array_merge([
            'company_id' => 1, 'type' => 'video', 'status' => 'pending', 'generation_engine' => 'mvm_ffmpeg', 'render_stage' => 'queued',
            'render_progress' => 0, 'regeneraciones' => 0, 'created_at' => now(), 'updated_at' => now(),
        ], $attrs));

        return GeneratedContent::find($id);
    }

    public function test_render_job_es_idempotente_si_ya_esta_ready(): void
    {
        $fila = $this->filaVideo(['status' => 'ready', 'output_path' => 'marketing/videos/1/qa2/previo.mp4', 'render_stage' => 'done']);
        $espia = new class(new FFmpegService(1), new TtsService(1), new AssetsEspia(1)) extends VideoTemplateRenderer {
            public int $llamadas = 0;

            public function render(VideoTemplate $template, array $variables, string $aspectRatio = '9:16', int $companyId = 1): array
            {
                $this->llamadas++;

                return ['success' => false, 'error' => 'no debía llamarse', 'log' => []];
            }
        };
        (new RenderVideoJob($fila->id, $this->plantillaGuion()->id, [], ['aspect_ratio' => '9:16'], 1))->handle($espia);
        $this->assertSame(0, $espia->llamadas, 'Un render ya listo no se vuelve a correr');
        $f = $fila->fresh();
        $this->assertSame('ready', $f->status);
        $this->assertSame('marketing/videos/1/qa2/previo.mp4', $f->output_path);
    }

    public function test_render_job_en_exito_guarda_log_voz_generada_y_costo(): void
    {
        $fila = $this->filaVideo();
        $falso = new class(new FFmpegService(1), new TtsService(1), new AssetsEspia(1)) extends VideoTemplateRenderer {
            public function render(VideoTemplate $template, array $variables, string $aspectRatio = '9:16', int $companyId = 1): array
            {
                return ['success' => true, 'output_path' => 'marketing/videos/1/qa2/x.mp4', 'thumbnail_path' => 'marketing/videos/1/qa2/x_thumb.jpg',
                    'duration_sec' => 12.5, 'cost_usd' => 0.0123, 'voz_generada' => false, 'log' => [['msg' => 'TTS WARNING: QA']]];
            }
        };
        (new RenderVideoJob($fila->id, $this->plantillaGuion()->id, [], ['aspect_ratio' => '9:16'], 1))->handle($falso);
        $f = $fila->fresh();
        $this->assertSame('ready', $f->status);
        $this->assertSame([['msg' => 'TTS WARNING: QA']], $f->render_log, 'En éxito se guarda el log');
        $this->assertFalse($f->generation_metadata['voz_generada']);
        $this->assertEqualsWithDelta(0.0123, (float) $f->generation_metadata['tts_cost_usd'], 0.00001, 'tts_cost_usd = cost_usd del render');
    }

    public function test_render_job_en_falla_llena_failure_reason_de_maximo_500(): void
    {
        $fila = $this->filaVideo();
        $falso = new class(new FFmpegService(1), new TtsService(1), new AssetsEspia(1)) extends VideoTemplateRenderer {
            public function render(VideoTemplate $template, array $variables, string $aspectRatio = '9:16', int $companyId = 1): array
            {
                return ['success' => false, 'error' => 'Scene 0 render failed ' . str_repeat('x', 900), 'log' => []];
            }
        };
        (new RenderVideoJob($fila->id, $this->plantillaGuion()->id, [], ['aspect_ratio' => '9:16'], 1))->handle($falso);
        $f = $fila->fresh();
        $this->assertSame('failed', $f->status);
        $this->assertNotEmpty($f->failure_reason);
        $this->assertLessThanOrEqual(500, mb_strlen($f->failure_reason));

        $fila2 = $this->filaVideo();
        (new RenderVideoJob($fila2->id, $this->plantillaGuion()->id, [], [], 1))->failed(new \RuntimeException('QA timeout'));
        $this->assertSame('failed', $fila2->fresh()->status);
        $this->assertNotEmpty($fila2->fresh()->failure_reason);
    }

    public function test_tts_caido_el_video_sale_sin_voz_y_queda_marcado(): void
    {
        $this->montarEscenarioFase2();
        $this->vozFalla = true;
        $renderer = new VideoTemplateRenderer(new FFmpegService(1), new TtsService(1), new AssetsEspia(1));
        $res = $renderer->render($this->plantillaGuion(), [
            '_escenas_mvm' => [
                ['type' => 'feature_highlight', 'duration' => 2, 'main_text' => 'Sin voz', 'sub_text' => ''],
                ['type' => 'call_to_action', 'duration' => 2, 'cta_text' => 'Llama', 'phone' => '{{brand.phone}}'],
            ], 'voiceover_text' => 'Voz que fallará',
        ], '9:16', 1);
        if (! empty($res['output_path'])) {
            $this->archivosQa[] = storage_path('app/' . $res['output_path']);
            $this->archivosQa[] = storage_path('app/' . ($res['thumbnail_path'] ?? 'x'));
        }
        $this->assertTrue($res['success'], (string) ($res['error'] ?? ''));
        $this->assertFalse($res['voz_generada'], 'Si el TTS falla, voz_generada = false (la ficha mostrará «sin voz»)');
        $this->assertNotEmpty($this->llamadasVoz);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/audio/speech'));
    }
}
