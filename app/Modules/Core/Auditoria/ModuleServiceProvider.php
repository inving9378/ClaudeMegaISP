<?php

namespace App\Modules\Core\Auditoria;

use App\Modules\BaseModuleServiceProvider;
use App\Modules\Core\Auditoria\Console\AuditoriaPermisosCaso0Command;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'core-auditoria';
    protected string $moduleType = 'core';
    protected ?string $viewNamespace = 'core-auditoria';

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditoriaPermisosCaso0Command::class,
            ]);
        }
    }
}
