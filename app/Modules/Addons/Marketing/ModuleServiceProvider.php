<?php

namespace App\Modules\Addons\Marketing;

use App\Modules\Addons\Marketing\Listeners\GatewayMessageListener;
use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppMediaReceived;
use App\Modules\Addons\WhatsAppAgent\Events\WhatsAppTextReceived;
use App\Modules\BaseModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-marketing';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-marketing';

    public function register(): void
    {
        parent::register();

        $this->app->register(Providers\MarketingServiceProvider::class);

        $this->mergeConfigFrom(base_path('config/marketing_gateway.php'), 'marketing_gateway');
    }

    public function boot(): void
    {
        parent::boot();

        // Adaptador hacia el gateway ÚNICO de WhatsApp (item roadmap #203, Fase
        // A). Inerte por default (config('marketing_gateway.mode') = 'legacy');
        // ver App\Modules\Addons\Marketing\Listeners\GatewayMessageListener.
        Event::listen(WhatsAppTextReceived::class, [GatewayMessageListener::class, 'handleText']);
        Event::listen(WhatsAppMediaReceived::class, [GatewayMessageListener::class, 'handleMedia']);
    }
}
