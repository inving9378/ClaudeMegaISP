<?php

namespace Database\Seeders\Core;

use App\Models\Core\ApiIntegration;
use App\Models\Core\ApiIntegrationLog;
use App\Models\Marketing\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Idempotent: only creates/updates integrations, never deletes keys from .env or marketing_settings.
 * Safe to re-run — uses updateOrCreate on company_id+slug.
 */
class MigrateExistingKeysToHubSeeder extends Seeder
{
    private int $companyId = 1;

    public function run(): void
    {
        $this->migrateClaude();
        $this->migrateOpenAi();
        $this->migrateEvolution();
        $this->migratePexels();
        $this->migrateGoogleMaps();

        $this->command->info('✅ Integration Hub: migration complete');
    }

    // ── Anthropic / Claude ──────────────────────────────────────────────────────

    private function migrateClaude(): void
    {
        $key = env('CLAUDE_API_KEY', '');

        $integration = $this->upsertIntegration([
            'provider'               => 'anthropic',
            'slug'                   => 'anthropic-default',
            'name'                   => 'Anthropic / Claude (principal)',
            'active'                 => true,
            'is_default_for_provider'=> true,
            'config'                 => [],
        ], $key ?: null);

        $this->command->info("  anthropic-default: " . ($key ? 'key migrated' : 'empty'));

        // Migrate ANTHROPIC_API_KEY as legacy if it exists and differs from the primary
        $legacyKey = env('ANTHROPIC_API_KEY', '');
        if ($legacyKey && $legacyKey !== $key) {
            $this->upsertIntegration([
                'provider'               => 'anthropic',
                'slug'                   => 'anthropic-legacy',
                'name'                   => 'Anthropic API Key (legacy .env)',
                'active'                 => false,
                'is_default_for_provider'=> false,
                'config'                 => ['note' => 'archived from ANTHROPIC_API_KEY .env variable'],
            ], $legacyKey);
            $this->command->info("  anthropic-legacy: archived (inactive)");
        }
    }

    // ── OpenAI ──────────────────────────────────────────────────────────────────

    private function migrateOpenAi(): void
    {
        // Prefer marketing_settings (may have been set via Brand Kit)
        $dbKey = $this->decryptSetting('openai_api_key');
        $envKey = env('OPENAI_API_KEY', '');
        $key = $dbKey ?: $envKey;

        $this->upsertIntegration([
            'provider'               => 'openai',
            'slug'                   => 'openai-default',
            'name'                   => 'OpenAI',
            'active'                 => (bool) $key,
            'is_default_for_provider'=> true,
            'config'                 => ['model' => 'tts-1', 'voice' => 'nova'],
        ], $key ?: null);

        $this->command->info("  openai-default: " . ($key ? 'key migrated' : 'empty (configure via UI)'));
    }

    // ── Evolution API / WhatsApp ────────────────────────────────────────────────

    private function migrateEvolution(): void
    {
        $dbKey = $this->decryptSetting('evolution_api_key');
        $envKey = env('WHATSAPP_API_KEY', '');
        $key = $dbKey ?: $envKey;

        $dbUrl = Setting::where('company_id', $this->companyId)->where('key', 'evolution_api_url')->value('value') ?? '';
        $dbInstance = Setting::where('company_id', $this->companyId)->where('key', 'evolution_instance_name')->value('value') ?? '';

        $this->upsertIntegration([
            'provider'               => 'evolution',
            'slug'                   => 'evolution-default',
            'name'                   => 'Evolution API / WhatsApp',
            'active'                 => (bool) $key,
            'is_default_for_provider'=> true,
            'config'                 => [
                'endpoint' => $dbUrl ?: env('WHATSAPP_API_URL', ''),
                'instance' => $dbInstance ?: env('WHATSAPP_DEFAULT_INSTANCE', ''),
            ],
        ], $key ?: null);

        $this->command->info("  evolution-default: " . ($key ? 'key migrated' : 'empty'));
    }

    // ── Pexels ──────────────────────────────────────────────────────────────────

    private function migratePexels(): void
    {
        $dbKey = $this->decryptSetting('pexels_api_key');
        $key = $dbKey ?: env('PEXELS_API_KEY', '');

        $this->upsertIntegration([
            'provider'               => 'pexels',
            'slug'                   => 'pexels-default',
            'name'                   => 'Pexels',
            'active'                 => (bool) $key,
            'is_default_for_provider'=> true,
            'config'                 => [],
        ], $key ?: null);

        $this->command->info("  pexels-default: " . ($key ? 'key migrated' : 'empty'));
    }

    // ── Google Maps ─────────────────────────────────────────────────────────────

    private function migrateGoogleMaps(): void
    {
        // .env has GOOGLE_MAPS_API_KEY twice — PHP env() returns the LAST value
        $key = env('GOOGLE_MAPS_API_KEY', '');

        $this->upsertIntegration([
            'provider'               => 'google_maps',
            'slug'                   => 'google-maps-default',
            'name'                   => 'Google Maps',
            'active'                 => (bool) $key,
            'is_default_for_provider'=> true,
            'config'                 => ['used_by' => ['backend', 'frontend']],
        ], $key ?: null);

        $this->command->info("  google-maps-default: " . ($key ? 'key migrated' : 'empty'));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function upsertIntegration(array $fields, ?string $plainKey): ApiIntegration
    {
        $record = ApiIntegration::updateOrCreate(
            ['company_id' => $this->companyId, 'slug' => $fields['slug']],
            array_merge(['company_id' => $this->companyId], $fields)
        );

        if ($plainKey) {
            $record->value = $plainKey;
            $record->save();
        } elseif (!$record->encrypted_value) {
            // No key — ensure preview/fingerprint are null
            $record->key_preview     = null;
            $record->key_fingerprint = null;
            $record->save();
        }

        // Log the migration event (audit trail, never logs key value)
        try {
            ApiIntegrationLog::firstOrCreate(
                ['integration_id' => $record->id, 'event_type' => 'key_migrated_from_legacy'],
                [
                    'company_id' => $this->companyId,
                    'actor'      => 'MigrateExistingKeysToHubSeeder',
                    'metadata'   => ['source' => $plainKey ? 'legacy_config' : 'empty'],
                ]
            );
        } catch (\Throwable) {}

        return $record;
    }

    private function decryptSetting(string $key): ?string
    {
        try {
            $setting = Setting::where('company_id', $this->companyId)->where('key', $key)->first();
            if (!$setting || !$setting->value) {
                return null;
            }
            if ($setting->encrypted) {
                return Crypt::decryptString($setting->value);
            }
            return $setting->value ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
