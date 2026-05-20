<?php

namespace App\Modules\Addons\Tickets;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-tickets';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-tickets';
}
