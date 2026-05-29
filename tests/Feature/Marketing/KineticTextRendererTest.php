<?php

namespace Tests\Feature\Marketing;

use App\Modules\Addons\Marketing\Services\Video\FFmpegService;
use App\Modules\Addons\Marketing\Services\Video\KineticTextRenderer;
use Tests\TestCase;

class KineticTextRendererTest extends TestCase
{
    public function test_render_produces_mp4_file(): void
    {
        $ffmpeg   = new FFmpegService(1);
        $renderer = new KineticTextRenderer($ffmpeg);

        // Create 5s blue background
        $basePath = storage_path('app/marketing/temp/test_kinetic_base_' . uniqid() . '.mp4');
        $outPath  = storage_path('app/marketing/temp/test_kinetic_out_' . uniqid() . '.mp4');

        $ffmpeg->exec([
            '-f', 'lavfi', '-i', 'color=c=blue:s=640x360:d=5',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', $basePath,
        ]);

        $result = $renderer->render(
            $basePath,
            [
                ['text' => 'HELLO', 'duration_sec' => 1.5, 'emphasis' => 'high', 'position' => 'center'],
                ['text' => 'WORLD', 'duration_sec' => 1.5, 'emphasis' => 'medium', 'position' => 'bottom'],
            ],
            ['font_size' => 48, 'color' => 'white'],
            $outPath
        );

        $this->assertTrue($result['success'], 'Render should succeed');
        $this->assertFileExists($outPath, 'Output MP4 should exist');
        $this->assertGreaterThan(1000, filesize($outPath), 'Output should not be empty');

        @unlink($basePath);
        @unlink($outPath);
    }

    public function test_empty_segments_returns_failure(): void
    {
        $ffmpeg   = new FFmpegService(1);
        $renderer = new KineticTextRenderer($ffmpeg);

        $result = $renderer->render('/nonexistent.mp4', [], ['font_size' => 48], '/tmp/out.mp4');

        $this->assertFalse($result['success']);
    }

    public function test_escape_drawtext_handles_special_chars(): void
    {
        $renderer  = new KineticTextRenderer(new FFmpegService(1));
        $reflector = new \ReflectionMethod($renderer, 'escapeDrawtext');
        $reflector->setAccessible(true);

        $escaped = $reflector->invoke($renderer, "100% FIBRA: sin 'lag'");
        $this->assertStringContainsString('\\%', $escaped);
        $this->assertStringContainsString("\\'", $escaped);
        $this->assertStringContainsString('\\:', $escaped);
    }
}
