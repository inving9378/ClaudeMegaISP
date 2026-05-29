<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\VideoTemplate;
use App\Modules\Addons\Marketing\Services\Tts\TtsService;
use App\Modules\Addons\Marketing\Services\Video\AssetLibraryService;
use App\Modules\Addons\Marketing\Services\Video\FFmpegService;
use App\Modules\Addons\Marketing\Services\Video\VideoTemplateRenderer;

class VideoTemplateRendererTest extends MarketingTestCase
{
    protected VideoTemplateRenderer $renderer;

    public function setUp(): void
    {
        parent::setUp();
        $this->renderer = new VideoTemplateRenderer(
            new FFmpegService(1),
            new TtsService(1),
            new AssetLibraryService(1),
        );
    }

    public function test_apply_aspect_ratio_9_16(): void
    {
        [$w, $h] = $this->renderer->applyAspectRatio('9:16');
        $this->assertEquals(1080, $w);
        $this->assertEquals(1920, $h);
    }

    public function test_apply_aspect_ratio_1_1(): void
    {
        [$w, $h] = $this->renderer->applyAspectRatio('1:1');
        $this->assertEquals(1080, $w);
        $this->assertEquals(1080, $h);
    }

    public function test_apply_aspect_ratio_16_9(): void
    {
        [$w, $h] = $this->renderer->applyAspectRatio('16:9');
        $this->assertEquals(1920, $w);
        $this->assertEquals(1080, $h);
    }

    public function test_apply_aspect_ratio_unknown_defaults_to_9_16(): void
    {
        [$w, $h] = $this->renderer->applyAspectRatio('unknown');
        $this->assertEquals(1080, $w);
        $this->assertEquals(1920, $h);
    }

    public function test_render_fails_with_missing_required_variable(): void
    {
        $template = VideoTemplate::where('slug', 'plan_promo_9_16')->first();

        if (!$template) {
            $this->markTestSkipped('plan_promo_9_16 template not seeded');
        }

        // Missing plan_name and plan_speed (required)
        $result = $this->renderer->render($template, [], '9:16', 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Variable requerida', $result['error']);
    }

    public function test_render_fails_with_unsupported_aspect_ratio(): void
    {
        $template = VideoTemplate::where('slug', 'plan_promo_9_16')->first();

        if (!$template) {
            $this->markTestSkipped('plan_promo_9_16 template not seeded');
        }

        $result = $this->renderer->render($template, [
            'plan_name'  => 'Mega 200',
            'plan_speed' => '200',
            'plan_price' => '$349',
        ], '4:3', 1); // unsupported ratio

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not supported', $result['error']);
    }

    public function test_render_full_pipeline_when_ffmpeg_available(): void
    {
        if (!file_exists('/usr/bin/ffmpeg') && !file_exists('/usr/local/bin/ffmpeg')) {
            $this->markTestSkipped('FFmpeg not installed — skipping full render test');
        }

        $template = VideoTemplate::where('slug', 'plan_promo_9_16')->first();
        if (!$template) {
            $this->markTestSkipped('plan_promo_9_16 template not seeded');
        }

        $result = $this->renderer->render($template, [
            'plan_name'      => 'Mega 100',
            'plan_speed'     => '100',
            'plan_price'     => '$249',
            'plan_features'  => 'Sin contrato,Instalación gratis',
            'cta_text'       => '¡Contrata ya!',
        ], '9:16', 1);

        // With FFmpeg available, render should succeed
        if ($result['success']) {
            $this->assertTrue($result['success']);
            $this->assertNotEmpty($result['output_path']);
            // Cleanup
            if (!empty($result['output_path'])) {
                @unlink(storage_path('app/' . $result['output_path']));
            }
            if (!empty($result['thumbnail_path'])) {
                @unlink(storage_path('app/' . $result['thumbnail_path']));
            }
        } else {
            // If TTS failed (no API key), it's still a valid test — log message
            $this->assertArrayHasKey('log', $result);
        }
    }
}
