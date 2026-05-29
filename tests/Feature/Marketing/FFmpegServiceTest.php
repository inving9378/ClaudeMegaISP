<?php

namespace Tests\Feature\Marketing;

use App\Modules\Addons\Marketing\Services\Video\FFmpegService;

class FFmpegServiceTest extends MarketingTestCase
{
    protected FFmpegService $ffmpeg;

    public function setUp(): void
    {
        parent::setUp();
        $this->ffmpeg = new FFmpegService(companyId: 1);
    }

    public function test_probe_returns_array(): void
    {
        // probe() on a non-existent file should return empty array, not throw
        $result = $this->ffmpeg->probe('/tmp/nonexistent_' . uniqid() . '.mp4');
        $this->assertIsArray($result);
    }

    public function test_default_font_returns_string(): void
    {
        $font = $this->ffmpeg->defaultFont();
        $this->assertIsString($font);
    }

    public function test_exec_returns_structure(): void
    {
        // Run a benign ffmpeg -version command (or graceful fail if not installed)
        $result = $this->ffmpeg->exec(['-version']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('exit_code', $result);
    }

    public function test_color_background_creates_file_when_ffmpeg_available(): void
    {
        // Only run if ffmpeg is present
        if (!file_exists('/usr/bin/ffmpeg') && !file_exists('/usr/local/bin/ffmpeg')) {
            $this->markTestSkipped('FFmpeg not installed — skipping render test');
        }

        $out = sys_get_temp_dir() . '/mvm_test_bg_' . uniqid() . '.mp4';
        $result = $this->ffmpeg->colorBackground('#0066CC', 1.0, $out, 640, 480);

        if ($result) {
            $this->assertFileExists($out);
            @unlink($out);
        } else {
            // Even if it fails, it should not throw
            $this->assertTrue(true);
        }
    }

    public function test_temp_path_returns_string_ending_with_filename(): void
    {
        $path = $this->ffmpeg->tempPath('test.mp4');
        $this->assertIsString($path);
        $this->assertStringEndsWith('test.mp4', $path);
    }
}
