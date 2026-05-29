<?php

namespace App\Modules\Addons\Marketing\Services\Publishing;

use App\Models\Core\ApiIntegration;
use App\Services\Core\ApiIntegrationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TokenRefresher
{
    private const GRAPH = 'https://graph.facebook.com/v18.0';

    public function __construct(private ApiIntegrationService $hub) {}

    /**
     * Revisa y renueva tokens de Meta que expiran pronto (< 7 días).
     * Retorna resumen de acciones tomadas.
     */
    public function refreshMetaTokens(int $companyId = 1): array
    {
        $integration = $this->hub->getIntegration('meta', $companyId);
        if (!$integration) {
            return ['skipped' => true, 'reason' => 'No hay integración meta configurada'];
        }

        $config = $integration->config ?? [];
        $appId     = $config['app_id'] ?? null;
        $appSecret = $config['app_secret'] ?? null;
        $userToken = $config['user_access_token'] ?? null;

        if (!$appId || !$appSecret || !$userToken) {
            return ['skipped' => true, 'reason' => 'Faltan app_id, app_secret o user_access_token'];
        }

        $actions = [];

        // Verificar expiración del user token
        $debugResp = Http::timeout(10)->get(self::GRAPH . '/debug_token', [
            'input_token'  => $userToken,
            'access_token' => "{$appId}|{$appSecret}",
        ]);

        if (!$debugResp->successful()) {
            return ['error' => 'No se pudo verificar token con Meta'];
        }

        $tokenData   = $debugResp->json()['data'] ?? [];
        $expiresAt   = $tokenData['expires_at'] ?? 0;
        $daysLeft    = $expiresAt ? (int) (($expiresAt - time()) / 86400) : null;

        if ($daysLeft !== null && $daysLeft < 7) {
            // Renovar long-lived token
            $refreshResp = Http::timeout(10)->get(self::GRAPH . '/oauth/access_token', [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => $appId,
                'client_secret'     => $appSecret,
                'fb_exchange_token' => $userToken,
            ]);

            if ($refreshResp->successful()) {
                $newToken = $refreshResp->json()['access_token'] ?? null;
                if ($newToken) {
                    $config['user_access_token'] = $newToken;
                    $integration->update(['config' => $config]);
                    $actions[] = "user_token renovado ({$daysLeft} días restantes → 60 días)";
                    Log::info('[TokenRefresher] Meta user token renovado', ['company_id' => $companyId]);
                }
            } else {
                $actions[] = 'ERROR renovando user_token: ' . ($refreshResp->json()['error']['message'] ?? 'unknown');
            }
        } else {
            $actions[] = "user_token OK ({$daysLeft} días restantes)";
        }

        // Re-obtener page_access_tokens para todos los channels Meta
        $this->refreshPageTokens($integration, $config, $companyId, $actions);

        return ['actions' => $actions, 'days_left' => $daysLeft];
    }

    private function refreshPageTokens(ApiIntegration $integration, array $config, int $companyId, array &$actions): void
    {
        $userToken = $config['user_access_token'] ?? null;
        if (!$userToken) {
            return;
        }

        $pagesResp = Http::timeout(10)->get(self::GRAPH . '/me/accounts', [
            'access_token' => $userToken,
            'fields'       => 'id,name,access_token',
        ]);

        if (!$pagesResp->successful()) {
            $actions[] = 'ERROR obteniendo páginas: ' . ($pagesResp->json()['error']['message'] ?? 'unknown');
            return;
        }

        $pages = $pagesResp->json()['data'] ?? [];
        $config['pages'] = $pages;
        $integration->update(['config' => $config]);

        // Actualizar tokens en los channels correspondientes
        foreach ($pages as $page) {
            $channels = \App\Models\Marketing\PublicationChannel::where('company_id', $companyId)
                ->where('platform', 'facebook')
                ->whereJsonContains('platform_config->page_id', $page['id'])
                ->get();

            foreach ($channels as $channel) {
                $channelConfig = $channel->platform_config ?? [];
                $channelConfig['page_access_token'] = $page['access_token'];
                $channel->update(['platform_config' => $channelConfig]);
                $actions[] = "page_access_token renovado para: {$page['name']}";
            }
        }
    }
}
