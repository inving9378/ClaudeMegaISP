<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Services\Tts\TtsService;

class TtsServiceTest extends MarketingTestCase
{
    public function test_default_driver_is_openai(): void
    {
        // Ensure no custom setting; service should default to openai
        $service = new TtsService(companyId: 999);
        $driver  = $service->getDriver();
        $this->assertEquals('openai', $driver->getDriverName());
    }

    public function test_piper_driver_resolved_by_name(): void
    {
        $service = new TtsService(companyId: 1);
        $driver  = $service->getDriver('piper');
        $this->assertEquals('piper', $driver->getDriverName());
    }

    public function test_unknown_driver_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $service = new TtsService(companyId: 1);
        $service->getDriver('nonexistent');
    }

    public function test_synthesize_returns_error_without_api_key(): void
    {
        // Company 999 has no OpenAI key configured
        $service = new TtsService(companyId: 999);
        $outPath = sys_get_temp_dir() . '/mvm_tts_test_' . uniqid() . '.mp3';

        $result = $service->synthesize('Hola mundo', $outPath, ['driver' => 'openai']);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_piper_driver_returns_error_when_binary_missing(): void
    {
        // Force piper binary to non-existent path
        Setting::firstOrCreate(
            ['company_id' => 99, 'key' => 'piper_binary_path'],
            ['company_id' => 99, 'key' => 'piper_binary_path', 'value' => '/nonexistent/piper', 'group' => 'tts', 'encrypted' => false]
        );
        Setting::firstOrCreate(
            ['company_id' => 99, 'key' => 'piper_model_path'],
            ['company_id' => 99, 'key' => 'piper_model_path', 'value' => '/nonexistent/model.onnx', 'group' => 'tts', 'encrypted' => false]
        );

        $service = new TtsService(companyId: 99);
        $outPath = sys_get_temp_dir() . '/mvm_piper_test_' . uniqid() . '.mp3';
        $result  = $service->synthesize('Hola', $outPath, ['driver' => 'piper']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', strtolower($result['error'] ?? ''));
    }

    public function test_list_voices_openai(): void
    {
        $service = new TtsService(companyId: 1);
        $voices  = $service->getDriver('openai')->listVoices();
        $this->assertIsArray($voices);
        $this->assertNotEmpty($voices);
        $ids = array_column($voices, 'id');
        $this->assertContains('nova', $ids);
    }

    public function test_list_voices_piper(): void
    {
        $service = new TtsService(companyId: 1);
        $voices  = $service->getDriver('piper')->listVoices();
        $this->assertIsArray($voices);
        $this->assertNotEmpty($voices);
    }
}
