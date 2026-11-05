<?php

namespace App\Modules\Addons\FirmaElectronica;

use App\Modules\Addons\FirmaElectronica\Contracts\FirmaProviderInterface;
use App\Modules\Addons\FirmaElectronica\Drivers\SandboxFirmaDriver;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-firma-electronica';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-firma-electronica';

    public function register(): void
    {
        parent::register();
        $this->app->bind(FirmaProviderInterface::class, function () {
            return match (config('firma_electronica.proveedor', 'sandbox')) {
                'sandbox' => new SandboxFirmaDriver(),
                default => throw new \RuntimeException('Proveedor de firma no soportado: ' . config('firma_electronica.proveedor')),
            };
        });
    }
}
