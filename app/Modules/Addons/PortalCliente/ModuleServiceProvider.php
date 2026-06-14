<?php

namespace App\Modules\Addons\PortalCliente;

use App\Modules\Addons\PortalCliente\Models\PortalClient;
use App\Modules\Addons\PortalCliente\Middleware\AuthPortal;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-portal-cliente';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-portal-cliente';

    public function register(): void
    {
        parent::register();

        // Registrar guard 'cliente' y provider 'portal_clients' en config/auth
        $this->app['config']->set('auth.guards.cliente', [
            'driver'   => 'session',
            'provider' => 'portal_clients',
        ]);

        $this->app['config']->set('auth.providers.portal_clients', [
            'driver' => 'eloquent',
            'model'  => PortalClient::class,
        ]);
    }

    public function boot(): void
    {
        parent::boot();

        // Registrar middleware de autenticación del portal
        $this->app['router']->aliasMiddleware('auth.portal', AuthPortal::class);
    }
}
