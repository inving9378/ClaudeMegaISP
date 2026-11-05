<?php

namespace App\Modules\Addons\CrmEmpresarial;

use App\Modules\Addons\CrmEmpresarial\Observers\CrmMainInformationObserver;
use App\Modules\BaseModuleServiceProvider;
use App\Modules\Core\CRM\Models\CrmMainInformation;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-crm-empresarial';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-crm-empresarial';

    public function boot(): void
    {
        parent::boot();
        if (! $this->moduleIsActive()) {
            return;
        }
        CrmMainInformation::observe(CrmMainInformationObserver::class);
    }
}
