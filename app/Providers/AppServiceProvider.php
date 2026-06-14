<?php

namespace App\Providers;

use App\Models\Marketing\Lead;
use App\Models\User;
use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use App\Observers\Marketing\LeadObserver;
use App\Observers\UserVoipObserver;
use App\Services\MikrotikService;
use Illuminate\Support\ServiceProvider;
use GuzzleHttp\Client;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerModuleProviders();

        $this->app->singleton(MikrotikService::class, function ($app) {
            return new MikrotikService();
        });

        // Timbrado CFDI 4.0 — stub hasta que se integre un PAC real
        $this->app->singleton(
            \App\Services\Finance\Timbrado\TimbradoServiceInterface::class,
            \App\Services\Finance\Timbrado\NullTimbradoService::class
        );

        // ── OLT drivers (B1b-4) ──────────────────────────────────────────────
        // Binding global intacto: todo el código existente sigue resolviendo
        // SmartOltDriver al pedir OltDriverInterface. NO tocar.
        $this->app->singleton(
            \App\Services\OltDriver\OltDriverInterface::class,
            \App\Services\OltDriver\SmartOltDriver::class
        );

        // Alias SmartOltDriver::class → mismo singleton que el binding global.
        $this->app->singleton(
            \App\Services\OltDriver\SmartOltDriver::class,
            fn($app) => $app->make(\App\Services\OltDriver\OltDriverInterface::class)
        );

        // HuaweiDriver: cadena TelnetSession → HuaweiTransport → HuaweiDriver
        // configurada desde config/services.php (OLT_HUAWEI_* en .env).
        // Si las credenciales no están configuradas, se usa un NullSession que
        // lanza RuntimeException en el primer intento de I/O.
        $this->app->bind(
            \App\Services\OltDriver\Huawei\HuaweiDriver::class,
            function ($app) {
                $cfg  = config('services.huawei_olt', []);
                $host = trim((string) ($cfg['host']     ?? ''));
                $user = trim((string) ($cfg['username'] ?? ''));
                $pass = (string)       ($cfg['password'] ?? '');

                $session = ($host !== '' && $user !== '' && $pass !== '')
                    ? new \App\Services\OltDriver\Huawei\TelnetSession($cfg)
                    : new class implements \App\Services\OltDriver\Huawei\SessionInterface {
                        public function write(string $data): void
                        {
                            throw new \RuntimeException(
                                'HuaweiDriver: OLT_HUAWEI_HOST / USER / PASS no configurados en .env'
                            );
                        }
                        public function readUntil(string $prompt, int $timeout = 30): string
                        {
                            throw new \RuntimeException(
                                'HuaweiDriver: OLT_HUAWEI_HOST / USER / PASS no configurados en .env'
                            );
                        }
                        public function close(): void {}
                      };

                $transport = new \App\Services\OltDriver\Huawei\HuaweiTransport(
                    config:  $cfg,
                    session: $session,
                );

                return new \App\Services\OltDriver\Huawei\HuaweiDriver(
                    transport: $transport,
                    config:    $cfg,
                );
            }
        );

        // Manager per-OLT — resuelve el driver correcto según Olt::$driver.
        $this->app->singleton(
            \App\Services\OltDriver\OltDriverManager::class,
            fn($app) => new \App\Services\OltDriver\OltDriverManager($app)
        );

        $this->app->singleton('SmartOlt', function () {
            // Credenciales: BD (olt_smartolt_config) tiene precedencia sobre .env.
            $dbCfg  = \App\Models\OltSmartoltConfig::isConfigured()
                ? \App\Models\OltSmartoltConfig::current()
                : null;

            $domain = $dbCfg ? $dbCfg->api_domain : config('services.smartolt.domain');
            $token  = $dbCfg ? $dbCfg->api_token  : config('services.smartolt.token');

            $data = [
                'base_uri' => "https://{$domain}.smartolt.com/api/",
                'headers'  => ['X-Token' => $token],
                'verify'   => env('VERIFY_SSL', true),
            ];
            if (env('PROXY') !== null) {
                $data['proxy'] = env('PROXY');
            }
            return new Client($data);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // View::share('configLayout', …) y View::share('logoMeganet', …)
        // se movieron a app/Modules/Core/Layout/ModuleServiceProvider::boot().
        Lead::observe(LeadObserver::class);
        \App\Models\Marketing\Message::observe(\App\Observers\Marketing\MessageObserver::class);

        // Embajadores Meganet
        \App\Models\Client::observe(\App\Observers\ClientObserver::class);

        // VoIP: ciclo de vida de extensión al cambiar estado del usuario
        User::observe(UserVoipObserver::class);
    }

    /**
     * Discover every app/Modules/{Core,Addons}/<Module>/ModuleServiceProvider.php
     * and register it. Each provider gates its own boot() via ModuleManagerService.
     *
     * Done in register() so that bindings + commands declared by module providers
     * are available before any boot() runs.
     */
    private function registerModuleProviders(): void
    {
        foreach (ModuleManagerService::instance()->discoverProviderClasses() as $providerClass) {
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }
}
