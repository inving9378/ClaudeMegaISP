<?php

namespace Tests\Feature\Marketing\Fase2\Backend;

use App\Models\Marketing\Asset;
use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\VideoTemplate;
use App\Modules\Addons\Marketing\Jobs\RenderVideoJob;
use App\Modules\Addons\Marketing\Services\Campanas360\GuionAMvm;
use App\Modules\Addons\Marketing\Services\Tts\TtsService;
use App\Modules\Addons\Marketing\Services\Video\AssetLibraryService;
use App\Modules\Addons\Marketing\Services\Video\FFmpegService;
use App\Modules\Addons\Marketing\Services\Video\VideoTemplateRenderer;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\TimeoutExceededException;
use Tests\Feature\Marketing\Fase2\Backend\Concerns\EscenarioBackendFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 2 · backend — cambios al motor (§2.6 [F2-1][F2-7]) SIN ffmpeg real: ffmpeg, TTS y biblioteca son dobles que
 * anotan lo que se les pide. Con la bandera: escenas de variables por lista blanca, duración = suma, voz y música por
 * video, música `type music`. Sin la bandera: la plantilla dibuja las escenas de su esquema como antes. Y el job:
 * idempotente, guarda log / voz_generada / costo, llena failure_reason y no marca error por un intento repetido.
 */
class RendererEscenasBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase2;

    private object $espia;

    private array $salidas = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->espia = (object) ['escenas' => [], 'tts' => [], 'assets' => [], 'mux' => [], 'archivos' => []];
    }

    protected function tearDown(): void
    {
        foreach ($this->salidas as $f) {
            @unlink(storage_path('app/' . $f));
        }
        foreach ($this->espia->archivos as $f) {
            @unlink($f);
        }
        array_map('unlink', glob(sys_get_temp_dir() . '/mkt-f2b-render/*') ?: []);
        parent::tearDown();
    }

    private function renderer(bool $ttsOk = true): VideoTemplateRenderer
    {
        $e = $this->espia;
        $tmp = sys_get_temp_dir() . '/mkt-f2b-render';
        @mkdir($tmp, 0775, true);

        $ffmpeg = new class($e, $tmp) extends FFmpegService {
            public function __construct(private object $e, private string $tmp) { parent::__construct(1); }
            private function t(string $p): bool { @touch($p); return true; }
            public function tempPath(string $filename): string { return $this->tmp . '/' . $filename; }
            public function exec(array $args, ?int $timeoutSec = 300): array { $this->t((string) end($args)); return ['success' => true]; }
            public function probe(string $filePath): array { return ['duration' => 10.0]; }
            public function concatVideos(array $inputPaths, string $outputPath): bool { return $this->t($outputPath); }
            public function concatVideosWithTransition(array $inputs, string $outputPath, string $transitionType = 'fade', float $transitionDuration = 0.5): array
            { $this->e->concat = [$transitionType, $transitionDuration]; $this->t($outputPath); return ['success' => true]; }
            public function muxAudio(string $videoPath, string $voicePath, ?string $musicPath, string $outputPath, float $voiceVolume = 1.0, float $musicVolume = 0.25): bool
            { $this->e->mux[] = ['musica' => $musicPath, 'vol' => $musicVolume]; return $this->t($outputPath); }
            public function applyFade(string $inputPath, string $outputPath, float $fadeInSec = 0.5, float $fadeOutSec = 0.5): bool { return $this->t($outputPath); }
            public function encodeForWeb(string $inputPath, string $outputPath, array $opts = []): bool { return $this->t($outputPath); }
            public function generateThumbnail(string $videoPath, string $outputPath, float $atSec = 1.0): bool { return $this->t($outputPath); }
            public function colorBackground(string $hexColor, float $durationSec, string $outputPath, int $width, int $height): bool { return $this->t($outputPath); }
        };

        $tts = new class($e, $ttsOk) extends TtsService {
            public function __construct(private object $e, private bool $ok) { parent::__construct(1); }
            public function synthesize(string $text, string $outputPath, array $options = []): array
            {
                $this->e->tts[] = ['texto' => $text, 'opciones' => $options];
                if (! $this->ok) {
                    return ['success' => false, 'error' => 'sin voz'];
                }
                @mkdir(dirname($outputPath), 0775, true);
                @touch($outputPath);
                $this->e->archivos[] = $outputPath;

                return ['success' => true, 'duration_sec' => 4.0, 'cost_usd' => 0.0125];
            }
        };

        $assets = new class($e) extends AssetLibraryService {
            public function __construct(private object $e) { parent::__construct(1); }
            public function getRandom(string $type, string $category, int $companyId = 1): ?Asset
            {
                $this->e->assets[] = [$type, $category];
                $a = new Asset();
                $a->path = 'marketing/assets/music/falsa.mp3';

                return $a;
            }
        };

        return new class($ffmpeg, $tts, $assets, $e) extends VideoTemplateRenderer {
            public function __construct(FFmpegService $f, TtsService $t, AssetLibraryService $a, private object $e) { parent::__construct($f, $t, $a); }
            protected function renderScene(array $scene, float $duration, string $outputPath, array $context, int $width, int $height): bool
            {
                $this->e->escenas[] = ['escena' => $scene, 'duracion' => $duration];
                @touch($outputPath);

                return true;
            }
        };
    }

    private function plantillaGuion(string $formato = '9:16'): VideoTemplate
    {
        return VideoTemplate::query()->where('company_id', 1)->where('slug', $formato === '9:16' ? 'guion-escenas-9-16' : 'guion-escenas-16-9')->firstOrFail();
    }

    private function variablesGuion(): array
    {
        return (new GuionAMvm())->traducir($this->guionValido(), '9:16')['variables'];
    }

    private function render(VideoTemplate $t, array $vars, string $ar = '9:16', bool $ttsOk = true): array
    {
        $r = $this->renderer($ttsOk)->render($t, $vars, $ar, 1);
        if (! empty($r['output_path'])) {
            $this->salidas[] = $r['output_path'];
            $this->salidas[] = $r['thumbnail_path'];
        }

        return $r;
    }

    public function test_con_bandera_las_escenas_vienen_del_guion_y_la_duracion_es_la_suma(): void
    {
        $r = $this->render($this->plantillaGuion(), $this->variablesGuion());

        $this->assertTrue($r['success'], $r['error'] ?? '');
        $this->assertSame(['broll_overlay', 'feature_highlight', 'call_to_action'], array_map(fn ($x) => $x['escena']['type'], $this->espia->escenas));
        $this->assertSame([5.0, 5.0, 6.0], array_column($this->espia->escenas, 'duracion'));
        $this->assertSame('{{brand.phone}}', $this->espia->escenas[2]['escena']['phone']);
        $this->assertSame(['fade', 0.3], $this->espia->concat);
        $this->assertTrue($r['voz_generada']);
        $this->assertSame(0.0125, $r['cost_usd']);
        $this->assertNotEmpty($r['log']);
    }

    public function test_voz_y_musica_por_video_y_musica_tipo_music(): void
    {
        $vars = $this->variablesGuion();
        $vars['_voz'] = 'onyx';

        $this->render($this->plantillaGuion(), $vars);

        $this->assertSame('onyx', $this->espia->tts[0]['opciones']['voice']);
        $this->assertSame('¿Otra vez se trabó la serie? Fibra de 500 megas. Contrata hoy, la instalación es gratis.', $this->espia->tts[0]['texto']);
        $this->assertSame([['music', 'uplifting_family']], $this->espia->assets);
        $this->assertSame(0.18, $this->espia->mux[0]['vol']);
        $this->assertNotNull($this->espia->mux[0]['musica']);
    }

    public function test_sin_musica_y_voz_fuera_de_lista_caen_al_respaldo(): void
    {
        $vars = $this->variablesGuion();
        $vars['_musica'] = null;
        $vars['_voz'] = 'robot';

        $this->render($this->plantillaGuion(), $vars);

        $this->assertSame([], $this->espia->assets, 'sin música no se busca nada');
        $this->assertNull($this->espia->mux[0]['musica']);
        // Voz fuera de lista: se ignora y queda la del nicho (si el nicho tiene una), nunca la pedida.
        $this->assertNotSame('robot', $this->espia->tts[0]['opciones']['voice'] ?? null);
    }

    public function test_lista_blanca_descarta_colores_tipos_y_fuentes_raras(): void
    {
        $vars = $this->variablesGuion();
        $vars['_escenas_mvm'] = [
            ['type' => 'feature_highlight', 'duration' => 4, 'main_text' => 'Hola {{brand.phone}}', 'bg_color' => "red'];drawtext", 'text_color' => 'x'],
            ['type' => 'split_screen', 'duration' => 4, 'text' => 'no permitido'],
            ['type' => 'broll_overlay', 'duration' => 40, 'broll_source' => 'file:/etc/passwd', 'kinetic_style' => ['color' => 'red'],
             'layers' => [['type' => 'kinetic_text', 'text' => 'Capa', 'style' => 'extreme', 'color' => 'red'], ['type' => 'kinetic_text', 'text' => 'Segunda']]],
            ['type' => 'call_to_action', 'duration' => 1, 'cta_text' => 'Ya', 'phone' => '{{_override_primary_color}}'],
            'basura',
        ];
        $vars['_override_primary_color'] = 'red';

        $this->render($this->plantillaGuion(), $vars);

        $escenas = array_column($this->espia->escenas, 'escena');
        $this->assertSame(['feature_highlight', 'broll_overlay', 'call_to_action'], array_column($escenas, 'type'));
        $this->assertSame(['type' => 'feature_highlight', 'duration' => 4, 'main_text' => 'Hola brand.phone'], $escenas[0]);
        $this->assertSame(['type' => 'broll_overlay', 'duration' => 15, 'layers' => [[
            'type' => 'kinetic_text', 'text' => 'Capa', 'duration_sec' => 15.0, 'style' => 'huge_centered', 'position' => 'center']]], $escenas[1]);
        $this->assertSame(['type' => 'call_to_action', 'duration' => 2, 'cta_text' => 'Ya', 'phone' => '{{brand.phone}}'], $escenas[2]);
        $this->assertStringNotContainsString('color', json_encode($escenas));
    }

    public function test_maximo_8_escenas_y_tope_de_75_segundos(): void
    {
        $vars = $this->variablesGuion();
        $vars['_escenas_mvm'] = array_fill(0, 10, ['type' => 'feature_highlight', 'duration' => 15, 'main_text' => 'X']);

        $r = $this->render($this->plantillaGuion('16:9'), $vars, '16:9');

        $this->assertTrue($r['success']);
        $this->assertCount(8, $this->espia->escenas);
        // 8 × 15 = 120 s, con tope de 75: cada escena conserva su duración, el total nominal se corta a 75 s.
        $this->assertSame(15.0, $this->espia->escenas[0]['duracion']);
    }

    public function test_sin_escenas_falla_con_mensaje_claro(): void
    {
        foreach ([[], null, 'texto', [['type' => 'split_screen', 'duration' => 3]]] as $escenas) {
            $vars = $this->variablesGuion();
            $vars['_escenas_mvm'] = $escenas;
            $r = $this->render($this->plantillaGuion(), $vars);
            $this->assertFalse($r['success']);
            $this->assertSame('El guion no trae escenas.', $r['error']);
        }
        $this->assertSame([], $this->espia->tts, 'no se gasta voz si no hay escenas');
    }

    public function test_guion_sin_voz_no_llama_al_tts_y_avisa_sin_voz(): void
    {
        $vars = $this->variablesGuion();
        $vars['voiceover_text'] = '';

        $r = $this->render($this->plantillaGuion(), $vars);

        $this->assertTrue($r['success']);
        $this->assertSame([], $this->espia->tts);
        $this->assertFalse($r['voz_generada']);
    }

    public function test_tts_que_falla_deja_voz_generada_false(): void
    {
        $r = $this->render($this->plantillaGuion(), $this->variablesGuion(), '9:16', false);

        $this->assertTrue($r['success']);
        $this->assertFalse($r['voz_generada']);
    }

    public function test_sin_bandera_la_plantilla_dibuja_sus_escenas_como_antes(): void
    {
        $t = VideoTemplate::create([
            'company_id' => 1, 'name' => 'Genérica F2B', 'slug' => 'generica-f2b-' . uniqid(), 'category' => 'generic',
            'aspect_ratios' => ['9:16'], 'duration_seconds' => 12, 'active' => true,
            'schema' => [
                'scenes' => [
                    ['type' => 'logo_reveal'],
                    ['type' => 'feature_highlight', 'main_text' => '{{headline}}', 'bg_color' => '#000000'],
                    ['type' => 'call_to_action', 'duration' => 3, 'cta_text' => 'Llama'],
                ],
                'voiceover_script' => '{{voiceover_text}}',
                'audio' => ['voiceover' => ['enabled' => true], 'background_music' => ['asset_category' => 'corporate_clean', 'volume' => 0.2]],
                // Aunque llegaran, en una plantilla sin bandera estas variables no cambian nada.
            ],
        ]);

        $r = $this->render($t, ['headline' => 'Titular', 'voiceover_text' => 'Hola', '_escenas_mvm' => [['type' => 'logo_reveal', 'duration' => 2]], '_voz' => 'onyx', '_musica' => 'epic_electronic']);

        $this->assertTrue($r['success']);
        $this->assertSame($t->schema['scenes'], array_column($this->espia->escenas, 'escena'));
        // Como antes: sin `duration` la escena toma schema.duration_seconds (15 por defecto) / número de escenas.
        $this->assertSame([5.0, 5.0, 3.0], array_column($this->espia->escenas, 'duracion'));
        $this->assertArrayNotHasKey('voice', $this->espia->tts[0]['opciones']);
        $this->assertSame([['music', 'corporate_clean']], $this->espia->assets, '[F2-7] música con type music, categoría del esquema');
        $this->assertTrue($r['voz_generada']);
    }

    // ── RenderVideoJob ─────────────────────────────────────────────────────────────────────────

    private function registro(string $status = 'pending'): GeneratedContent
    {
        $g = new GeneratedContent();
        $g->forceFill([
            'company_id' => 1, 'type' => 'video', 'status' => $status, 'template_id' => $this->plantillaGuion()->id,
            'template_type' => VideoTemplate::class, 'render_progress' => 0, 'render_stage' => 'queued', 'render_log' => [],
            'generation_engine' => 'mvm_ffmpeg', 'generation_cost_usd' => 0, 'generation_metadata' => ['aspect_ratio' => '9:16'],
        ])->save();

        return $g;
    }

    public function test_job_exitoso_guarda_log_voz_generada_y_costo(): void
    {
        $g = $this->registro();
        $job = new RenderVideoJob($g->id, $this->plantillaGuion()->id, $this->variablesGuion(), ['aspect_ratio' => '9:16'], 1);

        $job->handle($this->renderer(false));

        $g->refresh();
        $this->salidas[] = $g->output_path;
        $this->salidas[] = $g->thumbnail_path;
        $this->assertSame('ready', $g->status);
        $this->assertNotEmpty($g->render_log);
        $this->assertFalse($g->generation_metadata['voz_generada']);
        $this->assertSame('9:16', $g->generation_metadata['aspect_ratio']);
        $this->assertNull($g->failure_reason);

        $g2 = $this->registro();
        (new RenderVideoJob($g2->id, $this->plantillaGuion()->id, $this->variablesGuion(), ['aspect_ratio' => '9:16'], 1))->handle($this->renderer());
        $g2->refresh();
        $this->salidas[] = $g2->output_path;
        $this->salidas[] = $g2->thumbnail_path;
        $this->assertSame(0.0125, $g2->generation_metadata['tts_cost_usd']);
        $this->assertTrue($g2->generation_metadata['voz_generada']);
    }

    public function test_job_es_idempotente_si_ya_esta_listo(): void
    {
        $g = $this->registro('ready');
        $g->forceFill(['output_path' => 'marketing/videos/1/ya.mp4'])->save();

        (new RenderVideoJob($g->id, $this->plantillaGuion()->id, $this->variablesGuion(), ['aspect_ratio' => '9:16'], 1))->handle($this->renderer());

        $this->assertSame([], $this->espia->escenas);
        $this->assertSame('marketing/videos/1/ya.mp4', $g->fresh()->output_path);
    }

    public function test_job_que_falla_llena_failure_reason(): void
    {
        $g = $this->registro();
        $vars = $this->variablesGuion();
        $vars['_escenas_mvm'] = [];

        (new RenderVideoJob($g->id, $this->plantillaGuion()->id, $vars, ['aspect_ratio' => '9:16'], 1))->handle($this->renderer());

        $g->refresh();
        $this->assertSame('failed', $g->status);
        $this->assertSame('El guion no trae escenas.', $g->failure_reason);
    }

    public function test_failed_no_marca_error_a_un_video_listo_ni_al_que_otro_produce(): void
    {
        $job = fn (GeneratedContent $g) => new RenderVideoJob($g->id, 1, [], [], 1);

        $listo = $this->registro('ready');
        $job($listo)->failed(new \RuntimeException('x'));
        $this->assertSame('ready', $listo->fresh()->status);

        $produciendo = $this->registro('generating');
        $job($produciendo)->failed(new MaxAttemptsExceededException('intento repetido'));
        $this->assertSame('generating', $produciendo->fresh()->status);

        $colgado = $this->registro('generating');
        $job($colgado)->failed(new TimeoutExceededException('has timed out.'));
        $this->assertSame('failed', $colgado->fresh()->status);
        $this->assertSame('has timed out.', $colgado->fresh()->failure_reason);

        $otro = $this->registro('pending');
        $job($otro)->failed(new \RuntimeException(str_repeat('a', 700)));
        $this->assertSame(500, mb_strlen($otro->fresh()->failure_reason));
    }
}
