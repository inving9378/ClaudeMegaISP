<?php

namespace App\Services\Core;

trait UsesApiIntegration
{
    /**
     * Resolve an API key with Hub-first priority:
     * 1. Hub (api_integrations table, default for provider)
     * 2. env($envFallback)
     * 3. marketing_settings key $settingKey (if provided)
     */
    protected function resolveApiKey(
        string  $provider,
        string  $envFallback   = '',
        string  $settingKey    = '',
        int     $companyId     = 1
    ): ?string {
        // 1. Hub
        try {
            $key = ApiIntegrationService::instance()->getKey($provider, $companyId);
            if ($key) {
                return $key;
            }
        } catch (\Throwable) {
            // Hub table may not exist yet during fresh migrations
        }

        // 2. env
        if ($envFallback && $v = env($envFallback)) {
            return $v;
        }

        // 3. marketing_settings
        if ($settingKey) {
            try {
                return \App\Models\Marketing\Setting::get($settingKey, $companyId);
            } catch (\Throwable) {}
        }

        return null;
    }

    protected function resolveApiIntegration(string $provider, int $companyId = 1)
    {
        try {
            return ApiIntegrationService::instance()->getIntegration($provider, $companyId);
        } catch (\Throwable) {
            return null;
        }
    }
}
