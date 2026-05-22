<?php

namespace App\Modules\Addons\WhatsAppAgent;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-whatsapp-agent';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-whatsapp-agent';

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(
            base_path('config/whatsapp.php'),
            'whatsapp'
        );
    }
}
