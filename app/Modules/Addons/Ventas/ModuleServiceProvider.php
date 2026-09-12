<?php

namespace App\Modules\Addons\Ventas;

use App\Modules\Addons\Ventas\Console\ConsolidarProspectosCrmCommand;
use App\Modules\Addons\Ventas\Console\LiberarCustodiasVencidasCommand;
use App\Modules\Addons\Ventas\Observers\CrmLeadInformationObserver;
use App\Modules\Addons\Ventas\Observers\CrmMainInformationObserver;
use App\Modules\BaseModuleServiceProvider;
use App\Modules\Core\CRM\Models\CrmLeadInformation;
use App\Modules\Core\CRM\Models\CrmMainInformation;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-ventas';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-ventas';

    public function boot(): void
    {
        parent::boot();

        // Doble escritura hacia ventas_prospectos (catálogo único, item #9990779) mientras
        // CRM siga vivo.
        CrmLeadInformation::observe(CrmLeadInformationObserver::class);
        CrmMainInformation::observe(CrmMainInformationObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                LiberarCustodiasVencidasCommand::class,
                ConsolidarProspectosCrmCommand::class,
            ]);
        }
    }
}
