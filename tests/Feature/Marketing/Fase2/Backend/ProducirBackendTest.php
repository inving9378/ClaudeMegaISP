<?php

namespace Tests\Feature\Marketing\Fase2\Backend;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\VideoTemplate;
use App\Modules\Addons\Marketing\Jobs\RenderVideoJob;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Marketing\Fase2\Backend\Concerns\EscenarioBackendFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 2 · backend — «Producir en Video MVM» (§2.4.12–§2.4.14 y §2.6): variables exactas, cola `video-render`, un solo
 * trabajo por guion (409), validaciones, 404 cruzado, reproducir/miniatura solo cuando está listo y nunca fuera de la
 * carpeta de videos. Sin render real (Queue::fake).
 */
class ProducirBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase2;

    private array $archivos = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarNichos();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        foreach ($this->archivos as $f) {
            @unlink($f);
        }
        @rmdir(storage_path('app/marketing/videos/1/f2b-prueba'));
        parent::tearDown();
    }

    public function test_producir_reel_crea_un_trabajo_ligado_con_variables_exactas(): void
    {
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $guion = $this->crearPieza($c, 'reel_guion', 'A', $this->guionValido());
        $plantilla = VideoTemplate::query()->where('company_id', 1)->where('slug', 'guion-escenas-9-16')->firstOrFail();

        $r = $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/piezas/{$guion->id}/producir")->assertStatus(202);

        $r->assertJsonPath('data.estado', 'en_cola')
            ->assertJsonPath('data.estado_etiqueta', 'En cola')
            ->assertJsonPath('data.pieza', 'reel')
            ->assertJsonPath('data.guion_id', $guion->id)
            ->assertJsonPath('data.formato', '9:16')
            ->assertJsonPath('data.url_ver', null)
            ->assertJsonPath('data.demorado', false)
            ->assertJsonPath('data.sin_voz', false);

        $video = GeneratedContent::query()->findOrFail($r->json('data.id'));
        $this->assertSame('video', $video->type);
        $this->assertSame('reel', $video->pieza);
        $this->assertSame($guion->id, $video->pieza_origen_id);
        $this->assertSame($c->id, (int) $video->source_campaign_id);
        $this->assertSame($plantilla->id, (int) $video->template_id);
        $this->assertSame(VideoTemplate::class, $video->template_type);
        $this->assertSame('pending', $video->status);
        $this->assertSame('queued', $video->render_stage);
        $this->assertSame('mvm_ffmpeg', $video->generation_engine);
        $this->assertSame('guion-escenas-9-16', $video->generation_metadata['template_slug']);
        $this->assertSame(40, strlen($video->generation_metadata['guion_hash']));

        Queue::assertPushedOn('video-render', RenderVideoJob::class, function (RenderVideoJob $job) use ($video, $plantilla) {
            $v = $job->variables;

            return $job->generatedContentId === $video->id
                && $job->templateId === $plantilla->id
                && $job->options === ['aspect_ratio' => '9:16']
                && $job->companyId === 1
                && count($v['_escenas_mvm']) === 3
                && $v['_escenas_mvm'][0]['type'] === 'broll_overlay'
                && $v['_escenas_mvm'][2] === ['type' => 'call_to_action', 'duration' => 6, 'cta_text' => 'Contrata hoy', 'phone' => '{{brand.phone}}']
                && $v['voiceover_text'] === '¿Otra vez se trabó la serie? Fibra de 500 megas. Contrata hoy, la instalación es gratis.'
                && $v['_voz'] === 'nova'
                && $v['_musica'] === 'uplifting_family'
                && $v['_niche_slug'] === 'familia';
        });
        $empujado = Queue::pushed(RenderVideoJob::class)->first();
        $this->assertEquals($empujado->variables, $video->input_variables, 'lo guardado es lo que se manda al motor');
        Queue::assertPushed(RenderVideoJob::class, 1);

        // Visible también en Video → Cola (la API existente agrega source_campaign_id y pieza).
        $admin->givePermissionTo('view-video-content');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $cola = $this->actingAs($admin)->getJson('/api/marketing/generated-content')->assertOk();
        $fila = collect($cola->json('data'))->firstWhere('id', $video->id);
        $this->assertSame($c->id, $fila['source_campaign_id']);
        $this->assertSame('reel', $fila['pieza']);
    }

    public function test_video_16_9_con_voz_y_musica_elegidas(): void
    {
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $guion = $this->crearPieza($c, 'video_guion', 'A', $this->guionValido('16:9'));

        $r = $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/piezas/{$guion->id}/producir", ['voz' => 'onyx', 'musica' => 'sin_musica'])
            ->assertStatus(202)->assertJsonPath('data.formato', '16:9')->assertJsonPath('data.pieza', 'video');

        Queue::assertPushedOn('video-render', RenderVideoJob::class, fn (RenderVideoJob $j) => $j->options === ['aspect_ratio' => '16:9']
            && $j->variables['_voz'] === 'onyx' && $j->variables['_musica'] === null
            && $j->templateId === VideoTemplate::query()->where('slug', 'guion-escenas-16-9')->value('id'));
        $this->assertSame('guion-escenas-16-9', GeneratedContent::query()->find($r->json('data.id'))->generation_metadata['template_slug']);
    }

    public function test_doble_producir_da_409_y_una_sola_fila(): void
    {
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $guion = $this->crearPieza($c, 'reel_guion', 'A', $this->guionValido());
        $url = "/api/marketing/campanas-360/{$c->id}/piezas/{$guion->id}/producir";

        $this->actingAs($admin)->postJson($url)->assertStatus(202);
        $this->actingAs($admin)->postJson($url)->assertStatus(409)
            ->assertJsonPath('error', 'produccion_en_curso')->assertJsonPath('mensaje', 'Ese guion ya se está produciendo.');

        $this->assertSame(1, GeneratedContent::query()->where('pieza_origen_id', $guion->id)->count());
        Queue::assertPushed(RenderVideoJob::class, 1);

        // Terminado (listo o error), se puede volver a producir.
        GeneratedContent::query()->where('pieza_origen_id', $guion->id)->update(['status' => 'failed']);
        $this->actingAs($admin)->postJson($url)->assertStatus(202);
    }

    public function test_rechazos_de_producir(): void
    {
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $base = "/api/marketing/campanas-360/{$c->id}/piezas";

        $copy = $this->crearPieza($c, 'copy_facebook', 'A', ['texto' => 'x', 'hashtags' => [], 'cta' => '']);
        $this->actingAs($admin)->postJson("{$base}/{$copy->id}/producir")->assertStatus(422)->assertJsonPath('error', 'no_es_guion');

        $generando = $this->crearPieza($c, 'reel_guion', 'B', $this->guionValido(), 'generating');
        $this->actingAs($admin)->postJson("{$base}/{$generando->id}/producir")->assertStatus(422)->assertJsonPath('error', 'pieza_no_lista');

        $malo = $this->guionValido();
        $malo['escenas'][0]['duracion_s'] = 40;
        $invalido = $this->crearPieza($c, 'reel_guion', 'A', $malo);
        $this->actingAs($admin)->postJson("{$base}/{$invalido->id}/producir")->assertStatus(422)
            ->assertJsonPath('error', 'guion_invalido')->assertJsonStructure(['errores' => ['escenas.0.duracion_s']]);

        $ok = $this->crearPieza($c, 'video_guion', 'A', $this->guionValido('16:9'));
        $this->actingAs($admin)->postJson("{$base}/{$ok->id}/producir", ['voz' => 'robot'])->assertStatus(422)->assertJsonValidationErrors(['voz']);
        $this->actingAs($admin)->postJson("{$base}/{$ok->id}/producir", ['musica' => 'rock'])->assertStatus(422)->assertJsonValidationErrors(['musica']);

        VideoTemplate::query()->where('slug', 'guion-escenas-16-9')->update(['active' => false]);
        $this->actingAs($admin)->postJson("{$base}/{$ok->id}/producir")->assertStatus(422)->assertJsonPath('error', 'plantilla_no_disponible');

        Queue::assertNothingPushed();

        // Séptima petición en el mismo minuto: tope de F2-15 (throttle:6,1).
        $this->actingAs($admin)->postJson("{$base}/{$ok->id}/producir")->assertStatus(429);
    }

    public function test_pieza_de_otra_campana_da_404(): void
    {
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $otra = $this->crearCampana360($admin);
        $ajeno = $this->crearPieza($otra, 'reel_guion', 'A', $this->guionValido());

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/piezas/{$ajeno->id}/producir")->assertStatus(404)
            ->assertJsonPath('mensaje', 'No encontramos esa pieza en esta campaña.');
        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/99999999/piezas/{$ajeno->id}/producir")->assertStatus(404)
            ->assertJsonPath('mensaje', 'No encontramos esa campaña.');
        Queue::assertNothingPushed();
    }

    public function test_ver_y_miniatura_solo_cuando_esta_listo_y_dentro_de_la_carpeta(): void
    {
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $guion = $this->crearPieza($c, 'reel_guion', 'A', $this->guionValido());
        $id = $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/piezas/{$guion->id}/producir")->json('data.id');
        $base = "/api/marketing/campanas-360/{$c->id}/videos/{$id}";

        $this->actingAs($admin)->get("{$base}/ver")->assertStatus(404);

        $dir = storage_path('app/marketing/videos/1/f2b-prueba');
        @mkdir($dir, 0775, true);
        $nombre = Str::random(10);
        file_put_contents($this->archivos[] = "{$dir}/{$nombre}.mp4", 'mp4-falso');
        file_put_contents($this->archivos[] = "{$dir}/{$nombre}_thumb.jpg", 'jpg-falso');
        GeneratedContent::query()->whereKey($id)->update([
            'status'              => 'ready',
            'output_path'         => "marketing/videos/1/f2b-prueba/{$nombre}.mp4",
            'thumbnail_path'      => "marketing/videos/1/f2b-prueba/{$nombre}_thumb.jpg",
            'generation_metadata' => json_encode(['aspect_ratio' => '9:16', 'duration_sec' => 27.64, 'voz_generada' => false]),
        ]);

        $this->actingAs($admin)->get("{$base}/ver")->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->actingAs($admin)->get("{$base}/miniatura")->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $p = $this->actingAs($admin)->getJson("/api/marketing/campanas-360/{$c->id}/progreso")->assertOk();
        $this->assertSame('listo', $p->json('videos.0.estado'));
        $this->assertSame('27.6', $p->json('videos.0.duracion_s'));
        $this->assertTrue($p->json('videos.0.sin_voz'));
        $this->assertSame("/api/marketing/campanas-360/{$c->id}/videos/{$id}/ver", $p->json('videos.0.url_ver'));

        $ficha = $this->actingAs($admin)->getJson("/api/marketing/campanas-360/{$c->id}")->assertOk();
        $this->assertSame([$id], $ficha->json('data.piezas.reel_guion.0.video_ids'));
        $this->assertSame(['total' => 1, 'listos' => 1, 'en_proceso' => 0, 'error' => 0], $ficha->json('data.videos_resumen'));

        foreach (['../../../.env', 'marketing/videos/../../../.env', '/etc/passwd', "marketing/videos/1/f2b-prueba/{$nombre}.mp4\0"] as $malo) {
            GeneratedContent::query()->whereKey($id)->update(['output_path' => $malo]);
            $this->actingAs($admin)->get("{$base}/ver")->assertStatus(404);
        }

        $otra = $this->crearCampana360($admin);
        $this->actingAs($admin)->get("/api/marketing/campanas-360/{$otra->id}/videos/{$id}/miniatura")->assertStatus(404);
    }

    public function test_estados_de_video_demorado_y_error_traducido(): void
    {
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $guion = $this->crearPieza($c, 'reel_guion', 'A', $this->guionValido());
        $id = $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/piezas/{$guion->id}/producir")->json('data.id');
        $url = "/api/marketing/campanas-360/{$c->id}/progreso";

        GeneratedContent::query()->whereKey($id)->update(['created_at' => now()->subMinutes(11)]);
        $this->assertTrue($this->actingAs($admin)->getJson($url)->json('videos.0.demorado'));

        GeneratedContent::query()->whereKey($id)->update(['status' => 'generating', 'updated_at' => now()->subMinutes(16)]);
        $v = $this->actingAs($admin)->getJson($url)->json('videos.0');
        $this->assertSame(['produciendo', true], [$v['estado'], $v['demorado']]);

        GeneratedContent::query()->whereKey($id)->update(['status' => 'failed', 'failure_reason' => 'Scene 2 render failed at /tmp/x']);
        $v = $this->actingAs($admin)->getJson($url)->json('videos.0');
        $this->assertSame('error', $v['estado']);
        $this->assertSame('El motor de video falló al dibujar una escena. Intenta de nuevo.', $v['error']);
        $this->assertStringNotContainsString('/tmp', json_encode($v));
    }

    public function test_render_real_guarda_tamano_y_resolucion_reales(): void
    {
        if (! is_executable('/usr/bin/ffmpeg') && ! is_executable('/usr/local/bin/ffmpeg')) {
            $this->markTestSkipped('ffmpeg no instalado');
        }

        // Sin voz real: el TTS se sustituye por uno que falla (el video sale «sin voz», como en dev sin IA asignada).
        $this->app->instance(\App\Modules\Addons\Marketing\Services\Tts\TtsService::class, new class extends \App\Modules\Addons\Marketing\Services\Tts\TtsService {
            public function synthesize(string $text, string $outputPath, array $options = []): array
            {
                return ['success' => false, 'error' => 'sin voz en la prueba'];
            }
        });

        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $guion = $this->crearPieza($c, 'reel_guion', 'A', $this->guionValido());
        $id = $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$c->id}/piezas/{$guion->id}/producir")->assertStatus(202)->json('data.id');

        $job = Queue::pushed(RenderVideoJob::class)->first();
        app()->call([$job, 'handle']);

        $v = GeneratedContent::query()->findOrFail($id);
        $this->archivos[] = storage_path('app/' . $v->output_path);
        $this->archivos[] = storage_path('app/' . $v->thumbnail_path);

        $this->assertSame('ready', $v->status, (string) $v->failure_reason);
        $this->assertSame('1080x1920', $v->generation_metadata['resolution']);
        $this->assertSame(filesize(storage_path('app/' . $v->output_path)), $v->generation_metadata['file_size']);
        $this->assertGreaterThan(0, $v->generation_metadata['file_size']);
        $this->assertFalse($v->generation_metadata['voz_generada']);
    }

    public function test_el_render_no_puede_correr_dos_veces_a_la_vez(): void
    {
        $job = new RenderVideoJob(123, 1, [], ['aspect_ratio' => '9:16'], 1);
        $mw = $job->middleware();

        $this->assertCount(1, $mw);
        $this->assertInstanceOf(WithoutOverlapping::class, $mw[0]);
        $this->assertSame('mkt-render-123', $mw[0]->key);
        $this->assertSame(60, $mw[0]->releaseAfter);
        $this->assertSame(900, $mw[0]->expiresAfter);
        $this->assertSame(600, $job->timeout);
        $this->assertSame(2, $job->tries);
    }
}
