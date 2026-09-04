<?php

namespace App\Services\Core;

trait UsesApiIntegration
{
    /**
     * Mapa del nombre histórico de env() (parámetro $envFallback de las llamadas
     * existentes) a su equivalente ya migrado a config/. $envFallback llega como
     * variable (no un literal), así que no se puede reemplazar mecánicamente por
     * un env() literal — de ahí este mapa explícito (item #1000012, sub-item de
     * #984). Providers nuevos: agregar aquí su fila en vez de volver a leer
     * env() en runtime.
     */
    private const ENV_FALLBACK_CONFIG_MAP = [
        'CLAUDE_API_KEY'    => 'services.anthropic.key',
        'OPENAI_API_KEY'    => 'services.openai.key',
        'WHATSAPP_API_KEY'  => 'marketing.whatsapp_status_api_key',
        'WHATSAPP_API_BASE' => 'marketing.whatsapp_status_api_base',
    ];

    /**
     * Resolve an API key with Hub-first priority:
     * 1. Hub (api_integrations table, default for provider)
     * 2. config/ equivalent of the legacy $envFallback (ver ENV_FALLBACK_CONFIG_MAP)
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

        // 2. config (reemplaza el antiguo env($envFallback) dinámico)
        $configKey = self::ENV_FALLBACK_CONFIG_MAP[$envFallback] ?? null;
        if ($configKey && $v = config($configKey)) {
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
