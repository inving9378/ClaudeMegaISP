<?php

namespace App\Modules\Addons\Mensajes;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-mensajes';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-mensajes';
}
