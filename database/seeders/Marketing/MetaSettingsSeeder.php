<?php

namespace Database\Seeders\Marketing;

use App\Models\Marketing\Setting;
use Illuminate\Database\Seeder;

class MetaSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['group' => 'channels', 'key' => 'meta_app_id',            'value' => '',    'encrypted' => false],
            ['group' => 'channels', 'key' => 'meta_app_secret',        'value' => '',    'encrypted' => true],
            ['group' => 'channels', 'key' => 'meta_verify_token',      'value' => '',    'encrypted' => true],
            ['group' => 'channels', 'key' => 'meta_page_access_token', 'value' => '',    'encrypted' => true],
            ['group' => 'channels', 'key' => 'meta_lead_ads_form_ids', 'value' => '[]', 'encrypted' => false],
            ['group' => 'ai',       'key' => 'lead_scoring_enabled',         'value' => 'true', 'encrypted' => false],
            ['group' => 'ai',       'key' => 'lead_scoring_prompt_version',  'value' => 'v1',   'encrypted' => false],
        ];

        foreach ($settings as $s) {
            Setting::firstOrCreate(
                ['company_id' => 1, 'key' => $s['key']],
                array_merge($s, ['company_id' => 1])
            );
        }
    }
}
