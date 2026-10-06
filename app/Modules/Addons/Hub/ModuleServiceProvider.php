<?php

namespace App\Modules\Addons\Hub;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-hub';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-hub';

    public function register(): void
    {
        parent::register();

        // Catálogo de módulos que usan servicios externos → config('servicios_modulos')
        $this->mergeConfigFrom(__DIR__ . '/config/servicios_modulos.php', 'servicios_modulos');
    }
}
