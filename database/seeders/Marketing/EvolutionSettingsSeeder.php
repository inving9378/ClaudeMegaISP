<?php

namespace Database\Seeders\Marketing;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use App\Models\Marketing\Setting;

class EvolutionSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $webhookToken = Str::random(64);

        $settings = [
            // Evolution API connection
            ['group' => 'channels', 'key' => 'evolution_api_url',           'value' => '',                'encrypted' => false],
            ['group' => 'channels', 'key' => 'evolution_api_key',           'value' => '',                'encrypted' => true],
            ['group' => 'channels', 'key' => 'evolution_instance_name',     'value' => '',                'encrypted' => false],
            ['group' => 'channels', 'key' => 'evolution_webhook_token',     'value' => $webhookToken,     'encrypted' => true],

            // AI Agent settings
            ['group' => 'ai', 'key' => 'agent_model',                       'value' => 'claude-opus-4-7', 'encrypted' => false],
            ['group' => 'ai', 'key' => 'agent_max_tokens',                  'value' => '1024',            'encrypted' => false],
            ['group' => 'ai', 'key' => 'agent_temperature',                 'value' => '0.7',             'encrypted' => false],
            ['group' => 'ai', 'key' => 'agent_max_turns_safety',            'value' => '20',              'encrypted' => false],
            ['group' => 'ai', 'key' => 'agent_handoff_on_failure',          'value' => 'true',            'encrypted' => false],
            ['group' => 'ai', 'key' => 'agent_monthly_budget_usd',          'value' => '50.00',           'encrypted' => false],

            // Coverage zones (for CheckCoverageTool)
            ['group' => 'general', 'key' => 'coverage_zones',               'value' => '[]',              'encrypted' => false],
        ];

        foreach ($settings as $s) {
            $value = $s['value'];
            if ($s['encrypted'] && !empty($value)) {
                $value = Crypt::encryptString($value);
            }
            Setting::firstOrCreate(
                ['key' => $s['key'], 'company_id' => 1],
                [
                    'group'      => $s['group'],
                    'value'      => $value,
                    'encrypted'  => $s['encrypted'],
                    'company_id' => 1,
                ]
            );
        }

        // Store token plaintext temporarily so we can report it
        cache()->put('evolution_webhook_token_plain_' . now()->timestamp, $webhookToken, 300);
        $this->command->info("Evolution settings seeded.");
        $this->command->info("WEBHOOK TOKEN (save this for Evolution API config): {$webhookToken}");
    }
}
