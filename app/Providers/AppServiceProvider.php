<?php

namespace App\Providers;

use App\Models\Marketing\Lead;
use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use App\Observers\Marketing\LeadObserver;
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

        // OLT driver activo — GR-4b. Cambiar a HuaweiDriver (o a un OltDriverManager
        // multi-driver) cuando exista un segundo driver en el Bloque B.
        $this->app->singleton(
            \App\Services\OltDriver\OltDriverInterface::class,
            \App\Services\OltDriver\SmartOltDriver::class
        );

        $this->app->singleton('SmartOlt', function () {
            $data = [
                'base_uri' => "https://" . config('services.smartolt.domain') . ".smartolt.com/api/",
                'headers'  => ['X-Token' => config('services.smartolt.token')],
                'verify'   => env('VERIFY_SSL', true)
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
