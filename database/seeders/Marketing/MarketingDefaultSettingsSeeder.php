<?php

namespace Database\Seeders\Marketing;

use App\Models\Marketing\Setting;
use Illuminate\Database\Seeder;

class MarketingDefaultSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['group' => 'general', 'key' => 'module_name',        'value' => 'Marketing',                      'encrypted' => false],
            ['group' => 'general', 'key' => 'default_language',   'value' => 'es_MX',                          'encrypted' => false],
            ['group' => 'general', 'key' => 'default_currency',   'value' => 'MXN',                            'encrypted' => false],
            ['group' => 'ai',      'key' => 'claude_model',       'value' => 'claude-opus-4-7',                'encrypted' => false],
            ['group' => 'ai',      'key' => 'claude_api_key',     'value' => '',                               'encrypted' => true],
            ['group' => 'video',   'key' => 'ffmpeg_path',        'value' => '/usr/bin/ffmpeg',                'encrypted' => false],
            ['group' => 'video',   'key' => 'video_storage_path', 'value' => 'storage/app/marketing/videos',  'encrypted' => false],
            ['group' => 'video',   'key' => 'tts_provider',       'value' => 'piper',                         'encrypted' => false],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['company_id' => 1, 'key' => $setting['key']],
                array_merge($setting, ['company_id' => 1])
            );
        }
    }
}
