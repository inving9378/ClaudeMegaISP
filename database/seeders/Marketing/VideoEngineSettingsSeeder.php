<?php

namespace Database\Seeders\Marketing;

use App\Models\Marketing\Setting;
use Illuminate\Database\Seeder;

class VideoEngineSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // FFmpeg
            ['group' => 'video', 'key' => 'ffmpeg_binary',         'value' => '/usr/bin/ffmpeg',                          'encrypted' => false],
            ['group' => 'video', 'key' => 'ffprobe_binary',        'value' => '/usr/bin/ffprobe',                         'encrypted' => false],
            ['group' => 'video', 'key' => 'ffmpeg_threads',        'value' => '2',                                        'encrypted' => false],
            ['group' => 'video', 'key' => 'ffmpeg_preset',         'value' => 'fast',                                     'encrypted' => false],
            ['group' => 'video', 'key' => 'video_crf',             'value' => '23',                                       'encrypted' => false],
            ['group' => 'video', 'key' => 'video_max_bitrate',     'value' => '4000k',                                    'encrypted' => false],

            // TTS
            ['group' => 'tts',   'key' => 'tts_default_driver',    'value' => 'openai',                                   'encrypted' => false],
            ['group' => 'tts',   'key' => 'openai_api_key',        'value' => '',                                         'encrypted' => true],
            ['group' => 'tts',   'key' => 'openai_tts_model',      'value' => 'tts-1',                                    'encrypted' => false],
            ['group' => 'tts',   'key' => 'openai_tts_voice',      'value' => 'nova',                                     'encrypted' => false],
            ['group' => 'tts',   'key' => 'piper_binary_path',     'value' => '/usr/local/bin/piper',                     'encrypted' => false],
            ['group' => 'tts',   'key' => 'piper_model_path',      'value' => '/opt/piper-voices/es_MX-claude-high.onnx', 'encrypted' => false],

            // Brand
            ['group' => 'brand', 'key' => 'brand_company_name',    'value' => 'Meganet Telecomunicaciones',               'encrypted' => false],
            ['group' => 'brand', 'key' => 'brand_tagline',         'value' => 'Conectando tu mundo',                     'encrypted' => false],
            ['group' => 'brand', 'key' => 'brand_phone',           'value' => '800-MEGANET',                              'encrypted' => false],
            ['group' => 'brand', 'key' => 'brand_website',         'value' => 'www.meganet.com.mx',                       'encrypted' => false],
            ['group' => 'brand', 'key' => 'brand_primary_color',   'value' => '#0066CC',                                  'encrypted' => false],
            ['group' => 'brand', 'key' => 'brand_secondary_color', 'value' => '#FF6600',                                  'encrypted' => false],
            ['group' => 'brand', 'key' => 'brand_accent_color',    'value' => '#00CC66',                                  'encrypted' => false],
            ['group' => 'brand', 'key' => 'brand_text_color',      'value' => '#FFFFFF',                                  'encrypted' => false],
            ['group' => 'brand', 'key' => 'brand_logo_path',       'value' => 'marketing/brand/1/logo.png',               'encrypted' => false],

            // Pexels
            ['group' => 'media', 'key' => 'pexels_api_key',        'value' => '',                                         'encrypted' => true],
        ];

        foreach ($settings as $s) {
            Setting::firstOrCreate(
                ['company_id' => 1, 'key' => $s['key']],
                array_merge($s, ['company_id' => 1])
            );
        }
    }
}
