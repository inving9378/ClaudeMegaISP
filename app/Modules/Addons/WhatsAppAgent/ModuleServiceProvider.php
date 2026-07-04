<?php

namespace App\Modules\Addons\WhatsAppAgent;

use App\Modules\BaseModuleServiceProvider;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstanceFunction;
use App\Modules\Addons\WhatsAppAgent\Observers\WhatsAppInstanceFunctionObserver;
use App\Modules\Addons\WhatsAppAgent\Observers\WhatsAppInstanceObserver;

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

    public function boot(): void
    {
        parent::boot();

        // Capa de funciones (Fase 3) — observers backstop de las reglas 5 y 6.
        WhatsAppInstance::observe(WhatsAppInstanceObserver::class);
        WhatsAppInstanceFunction::observe(WhatsAppInstanceFunctionObserver::class);
    }
}
