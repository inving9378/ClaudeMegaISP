<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Core\ApiIntegration;
use App\Models\Marketing\PublicationChannel;
use App\Services\Core\ApiIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class MetaOAuthController extends Controller
{
    private const GRAPH = 'https://graph.facebook.com/v18.0';

    public function __construct(private ApiIntegrationService $hub) {}

    /**
     * GET /marketing/meta/oauth/start
     * Inicia el flujo OAuth de Facebook Login for Business.
     */
    public function start(Request $request)
    {
        $integration = $this->hub->getIntegration('meta', 1);
        if (!$integration) {
            return redirect('/marketing/publishing/setup')
                ->with('error', 'Primero configura la integración Meta en /integraciones con tu App ID y App Secret.');
        }

        $config = $integration->config ?? [];
        $appId  = $config['app_id'] ?? null;

        if (!$appId) {
            return redirect('/marketing/publishing/setup')
                ->with('error', 'La integración Meta no tiene app_id configurado.');
        }

        $state = csrf_token();
        Session::put('meta_oauth_state', $state);

        $scopes = implode(',', [
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',
            'pages_manage_metadata',
            'instagram_basic',
            'instagram_content_publish',
            'business_management',
            'public_profile',
            'email',
        ]);

        $url = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
            'client_id'    => $appId,
            'redirect_uri' => route('marketing.meta.oauth.callback'),
            'state'        => $state,
            'scope'        => $scopes,
            'response_type'=> 'code',
        ]);

        return redirect()->away($url);
    }

    /**
     * GET /marketing/meta/oauth/callback
     * Recibe el código OAuth, intercambia tokens, configura channels.
     */
    public function callback(Request $request)
    {
        // Validar state CSRF
        $state = $request->query('state');
        if (!$state || $state !== Session::pull('meta_oauth_state')) {
            return redirect('/marketing/publishing/setup')
                ->with('error', 'Estado OAuth inválido. Intenta de nuevo.');
        }

        $code = $request->query('code');
        if (!$code) {
            $errorMsg = $request->query('error_description', 'Autorización denegada por Meta.');
            return redirect('/marketing/publishing/setup')
                ->with('error', "Meta rechazó la autorización: {$errorMsg}");
        }

        $integration = $this->hub->getIntegration('meta', 1);
        $config      = $integration->config ?? [];
        $appId       = $config['app_id'];
        $appSecret   = $config['app_secret'];

        try {
            // 1. Intercambiar code por short-lived token
            $shortResp = Http::timeout(15)->get(self::GRAPH . '/oauth/access_token', [
                'client_id'     => $appId,
                'client_secret' => $appSecret,
                'redirect_uri'  => route('marketing.meta.oauth.callback'),
                'code'          => $code,
            ]);

            if (!$shortResp->successful()) {
                throw new \RuntimeException('Error obteniendo token corto: ' . $shortResp->body());
            }

            $shortToken = $shortResp->json()['access_token'];

            // 2. Intercambiar por long-lived token (60 días)
            $longResp = Http::timeout(15)->get(self::GRAPH . '/oauth/access_token', [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => $appId,
                'client_secret'     => $appSecret,
                'fb_exchange_token' => $shortToken,
            ]);

            if (!$longResp->successful()) {
                throw new \RuntimeException('Error obteniendo long-lived token: ' . $longResp->body());
            }

            $longToken = $longResp->json()['access_token'];

            // 3. Obtener user info
            $userResp = Http::timeout(10)->get(self::GRAPH . '/me', [
                'access_token' => $longToken,
                'fields'       => 'id,name,email',
            ]);
            $userId = $userResp->json()['id'] ?? null;

            // 4. Obtener páginas + sus page_access_tokens
            $pagesResp = Http::timeout(10)->get(self::GRAPH . '/me/accounts', [
                'access_token' => $longToken,
                'fields'       => 'id,name,access_token,instagram_business_account',
            ]);

            $pages = $pagesResp->json()['data'] ?? [];
            $igAccounts = [];

            foreach ($pages as &$page) {
                // 5. Para cada página, obtener IG Business account vinculada
                $igResp = Http::timeout(10)->get(self::GRAPH . "/{$page['id']}", [
                    'access_token' => $page['access_token'],
                    'fields'       => 'instagram_business_account',
                ]);
                $igId = $igResp->json()['instagram_business_account']['id'] ?? null;
                $page['ig_business_account_id'] = $igId;
                if ($igId) {
                    $igAccounts[] = $igId;
                }
            }

            // 6. Guardar todo en el config del Hub
            $config['user_access_token']      = $longToken;
            $config['user_id']                = $userId;
            $config['pages']                  = $pages;
            $config['instagram_accounts']     = $igAccounts;
            $config['approved_permissions']   = [];
            $integration->update(['config' => $config]);

            // 7. Configurar channels automáticamente
            $activatedChannels = $this->activateChannels($pages, $igAccounts);

            Log::info('[MetaOAuth] Conexión exitosa', [
                'user_id' => $userId,
                'pages'   => count($pages),
                'ig'      => count($igAccounts),
            ]);

            return redirect('/marketing/publishing/setup')
                ->with('success', "✓ Meta conectado exitosamente. {$activatedChannels} canales activados.");
        } catch (\Throwable $e) {
            Log::error('[MetaOAuth] callback error', ['e' => $e->getMessage()]);
            return redirect('/marketing/publishing/setup')
                ->with('error', 'Error en OAuth: ' . $e->getMessage());
        }
    }

    /**
     * Configura los channels de publicación con los datos de Meta.
     */
    private function activateChannels(array $pages, array $igAccounts): int
    {
        $activated = 0;
        $page      = $pages[0] ?? null;
        if (!$page) {
            return 0;
        }

        $fbChannels = PublicationChannel::where('platform', 'facebook')->get();
        foreach ($fbChannels as $ch) {
            $config = $ch->platform_config ?? [];
            $config['page_id']           = $page['id'];
            $config['page_access_token'] = $page['access_token'];
            $config['page_name']         = $page['name'];
            $ch->update([
                'platform_config'            => $config,
                'external_id'               => $page['id'],
                'external_name'             => $page['name'],
                'credentials_ready'          => true,
                'credentials_status_message' => 'Conectado via OAuth',
                'credentials_validated_at'   => now(),
            ]);
            $activated++;
        }

        $igUserId = $igAccounts[0] ?? $page['ig_business_account_id'] ?? null;
        if ($igUserId) {
            $igChannels = PublicationChannel::where('platform', 'instagram')->get();
            foreach ($igChannels as $ch) {
                $config = $ch->platform_config ?? [];
                $config['ig_user_id']        = $igUserId;
                $config['page_access_token'] = $page['access_token'];
                $ch->update([
                    'platform_config'            => $config,
                    'external_id'               => $igUserId,
                    'credentials_ready'          => true,
                    'credentials_status_message' => 'Conectado via OAuth',
                    'credentials_validated_at'   => now(),
                ]);
                $activated++;
            }
        }

        return $activated;
    }
}
