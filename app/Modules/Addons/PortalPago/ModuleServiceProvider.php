<?php

namespace App\Modules\Addons\PortalPago;

use App\Modules\BaseModuleServiceProvider;

/**
 * Portal de Pago Meganet — cobro SPEI + conciliación por CEP de Banxico.
 *
 * El boot() heredado carga routes.php, views/ y migrations/ del módulo cuando
 * está activo en module_registry. Las migraciones de este addon viven en
 * database/migrations/ (precedente Domiciliación), por lo que aquí no hay
 * carpeta migrations/.
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-portal-pago';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-portal-pago';

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\Addons\PortalPago\Commands\EnviarRecurrentesCommand::class,
            ]);
        }
    }
}
