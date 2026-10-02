<?php

namespace App\Modules\Addons\IA;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-ia';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-ia';

    public function register(): void
    {
        parent::register();

        // Catálogo de puntos de uso de IA asignables por módulo → config('ia_modulos')
        $this->mergeConfigFrom(__DIR__ . '/config/modulos.php', 'ia_modulos');
    }
}
