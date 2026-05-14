<?php

namespace App\Providers;

use App\Models\AppLayoutConfiguration;
use App\Models\CompanyInformation;
use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
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
        View::share('configLayout', function ($id) {
            if (Auth::check()) {
                $configLayout = AppLayoutConfiguration::where('user_id', $id)->first();
            }
            return $configLayout;
        });

        View::share('logoMeganet', function () {
            //TODO ajustar para cuando se despliegue
            $companyInformation = CompanyInformation::first();
            if ($companyInformation) {
                $logo = [
                    'name' => $companyInformation->logo,
                    'url_logo' => $companyInformation->url_logo
                ];
                return $logo;
            }
            $logo = [
                'name' => null,
                'url_logo' => ''
            ];
            return $logo;
        });
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
