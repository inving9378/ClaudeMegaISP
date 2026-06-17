<?php

namespace App\Modules\Addons\Domiciliacion;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-domiciliacion';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-domiciliacion';

    public function boot(): void
    {
        parent::boot();

        if ($this->moduleIsActive()) {
            $this->commands([
                Commands\DomiciliacionCobrarCommand::class,
            ]);
        }
    }
}
