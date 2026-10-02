<?php

namespace App\Services\Core;

use App\Models\Core\ApiIntegration;
use App\Models\Core\ApiIntegrationLog;
use App\Models\Core\ApiIntegrationUsage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiIntegrationService
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    // ── Key retrieval ───────────────────────────────────────────────────────────

    public function getKey(string $provider, int $companyId = 1): ?string
    {
        return $this->getIntegration($provider, $companyId)?->value;
    }

    public function getIntegration(string $provider, int $companyId = 1): ?ApiIntegration
    {
        return ApiIntegration::forCompany($companyId)
            ->defaultFor($provider)
            ->first();
    }

    public function getBySlug(string $slug, int $companyId = 1): ?ApiIntegration
    {
        return ApiIntegration::forCompany($companyId)->where('slug', $slug)->first();
    }

    // ── Usage tracking ──────────────────────────────────────────────────────────

    public function trackUsage(ApiIntegration $integration, string $feature, int $calls = 1, float $costUsd = 0.0): void
    {
        try {
            ApiIntegrationUsage::upsert(
                [[
                    'integration_id' => $integration->id,
                    'company_id'     => $integration->company_id,
                    'usage_date'     => now()->toDateString(),
                    'feature'        => $feature,
                    'call_count'     => $calls,
                    'cost_usd'       => $costUsd,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]],
                ['integration_id', 'usage_date', 'feature'],
                ['call_count' => \DB::raw("call_count + {$calls}"), 'cost_usd' => \DB::raw("cost_usd + {$costUsd}"), 'updated_at' => now()]
            );
        } catch (\Throwable $e) {
            Log::warning('ApiIntegrationService: trackUsage failed', ['error' => $e->getMessage()]);
        }
    }

    // ── Audit logging ───────────────────────────────────────────────────────────

    public function audit(ApiIntegration $integration, string $eventType, array $metadata = [], ?string $actor = null): void
    {
        try {
            ApiIntegrationLog::create([
                'integration_id' => $integration->id,
                'company_id'     => $integration->company_id,
                'event_type'     => $eventType,
                'actor'          => $actor ?? (auth()->user()?->email ?? 'system'),
                'metadata'       => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ApiIntegrationService: audit failed', ['error' => $e->getMessage()]);
        }
    }

    // ── Validation ──────────────────────────────────────────────────────────────

    public function validate(ApiIntegration $integration): array
    {
        $key = $integration->value;

        if (!$key) {
            return $this->validationResult($integration, false, 'No key configured');
        }

        $validators = [
            'anthropic'   => fn() => $this->validateAnthropic($key),
            'openai'      => fn() => $this->validateOpenAi($key),
            'evolution'   => fn() => $this->validateEvolution($key, $integration->config ?? []),
            'pexels'      => fn() => $this->validatePexels($key),
            'google_maps' => fn() => $this->validateGoogleMaps($key),
        ];

        // Sin validador para este proveedor: NO se marca como válida (sería mentir)
        if (!isset($validators[$integration->provider])) {
            $this->audit($integration, 'validation_skipped', ['message' => 'Proveedor sin validación automática']);
            return [
                'success' => false,
                'message' => 'Este proveedor no tiene validación automática; la key no se verificó',
                'status'  => $integration->last_validation_status,
            ];
        }

        try {
            $ok = $validators[$integration->provider]();
        } catch (\Throwable $e) {
            return $this->validationResult($integration, false, $e->getMessage());
        }

        return $this->validationResult($integration, $ok, $ok ? 'OK' : 'Validation failed');
    }

    private function validationResult(ApiIntegration $integration, bool $ok, string $message): array
    {
        $status = $ok ? 'valid' : 'invalid';
        $integration->update([
            'last_validation_status' => $status,
            'last_validated_at'      => now(),
        ]);
        $this->audit($integration, $ok ? 'validated' : 'validation_failed', ['message' => $message]);
        return ['success' => $ok, 'message' => $message, 'status' => $status];
    }

    private function validateAnthropic(string $key): bool
    {
        $res = Http::timeout(10)
            ->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 1,
                'messages'   => [['role' => 'user', 'content' => 'hi']],
            ]);
        return in_array($res->status(), [200, 400]);
    }

    private function validateOpenAi(string $key): bool
    {
        $res = Http::timeout(10)
            ->withToken($key)
            ->get('https://api.openai.com/v1/models');
        return $res->status() === 200;
    }

    private function validateEvolution(string $key, array $config): bool
    {
        $url = rtrim($config['endpoint'] ?? config('whatsapp.api_url', ''), '/');
        if (!$url) {
            return false;
        }
        $res = Http::timeout(10)
            ->withHeaders(['apikey' => $key])
            ->get("{$url}/instance/fetchInstances");
        return $res->successful();
    }

    private function validatePexels(string $key): bool
    {
        $res = Http::timeout(10)
            ->withHeaders(['Authorization' => $key])
            ->get('https://api.pexels.com/v1/search', ['query' => 'test', 'per_page' => 1]);
        return $res->status() === 200;
    }

    private function validateGoogleMaps(string $key): bool
    {
        $res = Http::timeout(10)
            ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => 'Mexico',
                'key'     => $key,
            ]);
        return isset($res->json()['status']) && $res->json()['status'] !== 'REQUEST_DENIED';
    }

    // ── Provider catalog ────────────────────────────────────────────────────────

    public function getProviders(): array
    {
        // Activos + los inactivos que aún tienen integraciones (para no esconderlas de la UI)
        $withIntegrations = ApiIntegration::query()->distinct()->pluck('provider');

        return \App\Models\Core\ApiIntegrationProvider::query()
            ->where(fn($q) => $q->where('active', true)->orWhereIn('slug', $withIntegrations))
            ->orderBy('type')->orderBy('name')->get()
            ->map(fn($p) => [
                'id'          => $p->slug,
                'name'        => $p->name,
                'description' => $p->description,
                'type'        => $p->type,
                'icon'        => $p->icon,
                'docs_url'    => $p->docs_url,
                'key_format'  => $p->key_format,
                'has_config'  => $p->has_config,
                'active'      => $p->active,
            ])->all();
    }
}
