<?php

namespace App\Modules\Addons\EvaluadorEmpresarial;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-evaluador-empresarial';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-evaluador-empresarial';
}
