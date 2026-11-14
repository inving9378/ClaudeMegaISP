<?php

namespace Tests\Feature\Marketing\Fase2\Qa;

use App\Modules\Addons\Marketing\Services\Video\FFmpegService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase2\Qa\Concerns\EscenarioFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 2 — CRITERIO DE HENRY, de punta a punta (contrato §5, criterio 7a).
 *
 * IA y voz SIMULADAS, ffmpeg REAL: crear una campaña con `reels` y `video` por la API → generar con IA →
 * producir el Reel A y el Video A desde la campaña → correr `RenderVideoJob` en este proceso → ambos `ready`,
 * el MP4 existe, `ffprobe` da 1080×1920 y 1920×1080, duración = suma de escenas ± ((n−1)×0.3 + 1) s, con pista
 * de audio, ligados a la campaña y visibles en `progreso` como `listo`, reproducibles por `ver`. Borra los
 * archivos al terminar. Se corre con `timeout 900`.
 */
class ProduccionPuntaAPuntaTest extends MarketingTestCase
{
    use EscenarioFase2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioFase2();
    }

    protected function tearDown(): void
    {
        $this->limpiarArchivosQa();
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function ffprobe(string $archivo): array
    {
        $out = shell_exec('ffprobe -v error -show_entries stream=codec_type,width,height:format=duration -of json ' . escapeshellarg($archivo));

        return json_decode((string) $out, true) ?: [];
    }

    public function test_una_campana_produce_un_reel_9_16_y_un_video_16_9_reales(): void
    {
        $this->assertFileExists('/usr/bin/ffmpeg');

        // 1. Alta por la API (asistente) + generar con IA
        $id = $this->campanaGenerada(['canales' => ['reels', 'video', 'whatsapp'], 'presupuesto_mxn' => '1234.56']);
        $ficha = $this->como($this->admin)->getJson("/api/marketing/campanas-360/{$id}")->assertStatus(200);
        $this->assertSame('lista', $ficha->json('data.generacion.estado'));
        $this->assertSame('1234.56', $ficha->json('data.presupuesto_mxn'));

        $guiones = [
            'reel' => ['id' => $this->piezaId($id, 'reel_guion'), 'w' => 1080, 'h' => 1920],
            'video' => ['id' => $this->piezaId($id, 'video_guion'), 'w' => 1920, 'h' => 1080],
        ];

        // 2. Producir desde la campaña (sin copiar y pegar)
        $videos = [];
        foreach ($guiones as $tipo => $g) {
            $r = $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/piezas/{$g['id']}/producir", []);
            $r->assertStatus(202)->assertJsonPath('data.estado', 'en_cola');
            $videos[$tipo] = (int) $r->json('data.id');
        }
        Queue::assertPushedOn('video-render', self::JOB_RENDER);

        // 3. El procesador de video-render (aquí, en este proceso)
        $t0 = microtime(true);
        $errores = $this->correrTrabajos([self::JOB_RENDER]);
        $segundos = round(microtime(true) - $t0, 1);
        fwrite(STDERR, "\n[QA punta a punta] render de Reel + Video: {$segundos} s\n");
        $this->assertSame([], $errores, implode(' | ', $errores));

        // 4. Resultado
        $progreso = $this->como($this->admin)->getJson("/api/marketing/campanas-360/{$id}/progreso")->assertStatus(200);
        $porId = collect($progreso->json('videos'))->keyBy('id');
        foreach ($guiones as $tipo => $g) {
            $fila = DB::table('marketing_generated_content')->find($videos[$tipo]);
            $this->assertSame('ready', $fila->status, "{$tipo}: " . $fila->failure_reason . ' ' . $fila->render_log);
            $this->assertSame($id, (int) $fila->source_campaign_id);
            $this->assertSame($g['id'], (int) $fila->pieza_origen_id);
            $this->assertSame($tipo, $fila->pieza);
            $mp4 = storage_path('app/' . $fila->output_path);
            $this->archivosQa[] = $mp4;
            $this->archivosQa[] = storage_path('app/' . $fila->thumbnail_path);
            $this->assertFileExists($mp4);
            $this->assertFileExists(storage_path('app/' . $fila->thumbnail_path));

            $info = $this->ffprobe($mp4);
            $video = collect($info['streams'] ?? [])->firstWhere('codec_type', 'video');
            $audio = collect($info['streams'] ?? [])->firstWhere('codec_type', 'audio');
            $this->assertSame($g['w'], (int) $video['width'], "{$tipo}: ancho");
            $this->assertSame($g['h'], (int) $video['height'], "{$tipo}: alto");
            $this->assertNotNull($audio, "{$tipo}: el MP4 trae pista de audio (voz)");

            $contenido = json_decode((string) DB::table('marketing_generated_content')->where('id', $g['id'])->value('contenido'), true);
            $n = count($contenido['escenas']);
            $suma = array_sum(array_column($contenido['escenas'], 'duracion_s'));
            $this->assertSame($suma, (int) $contenido['duracion_total_s']);
            $dur = (float) ($info['format']['duration'] ?? 0);
            $this->assertEqualsWithDelta($suma, $dur, ($n - 1) * 0.3 + 1, "{$tipo}: duración {$dur} vs suma {$suma}");

            $meta = json_decode((string) $fila->generation_metadata, true);
            $this->assertTrue($meta['voz_generada'] ?? null, "{$tipo}: la voz se generó (TTS simulado)");

            $v = $porId[$videos[$tipo]] ?? null;
            $this->assertNotNull($v, "{$tipo}: visible en progreso");
            $this->assertSame('listo', $v['estado']);
            $this->assertFalse($v['sin_voz']);
            $this->assertNotNull($v['url_ver']);
            $ver = $this->como($this->admin)->get($v['url_ver']);
            $ver->assertStatus(200);
            $this->assertStringStartsWith('video/mp4', (string) $ver->headers->get('Content-Type'));
            $this->como($this->admin)->get($v['url_miniatura'])->assertStatus(200);
        }
        $this->assertCount(2, $this->llamadasVoz, 'Una voz por video');
        $this->assertSame('nova', $this->llamadasVoz[0]['voice'] ?? null);
        $this->assertSame([], $this->peticionesAjenas, 'Nada salió fuera de la IA simulada');

        // 5. Un render que se vuelve a entregar no corre dos veces (idempotencia)
        foreach (Queue::pushed(self::JOB_RENDER) as $job) {
            $antes = DB::table('marketing_generated_content')->where('id', $job->generatedContentId)->value('output_path');
            app()->call([$job, 'handle']);
            $this->assertSame($antes, DB::table('marketing_generated_content')->where('id', $job->generatedContentId)->value('output_path'), 'Re-entrega no vuelve a renderizar');
        }

        $resumen = $this->como($this->admin)->getJson("/api/marketing/campanas-360/{$id}")->json('data.videos_resumen');
        $this->assertSame(['total' => 2, 'listos' => 2, 'en_proceso' => 0, 'error' => 0], $resumen);
        $this->assertNotEmpty((new FFmpegService(1))->probe(storage_path('app/' . DB::table('marketing_generated_content')->where('id', $videos['reel'])->value('output_path'))));
    }
}
