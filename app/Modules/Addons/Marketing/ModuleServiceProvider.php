<?php

namespace App\Modules\Addons\Marketing;

use App\Modules\Addons\Marketing\Listeners\GatewayMessageListener;
use App\Modules\Addons\Marketing\Middleware\RequiereRolEmpresa;
use App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva;
use App\Modules\Addons\Marketing\Middleware\SoloEmpresaPrincipal;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
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

        // Fase 0 (Marketing IA 360): empresa activa de la petición. scoped() ⇒ se limpia sola
        // entre peticiones y entre jobs de cola (nunca se arrastra a webhooks/jobs/comandos).
        $this->app->scoped(EmpresaActiva::class);

        $this->mergeConfigFrom(base_path('config/marketing_gateway.php'), 'marketing_gateway');
    }

    public function boot(): void
    {
        parent::boot();

        // Fase 0 (Marketing IA 360): capa por empresa de las rutas autenticadas del módulo.
        $router = $this->app['router'];
        $router->aliasMiddleware('marketing.empresa', ResolverEmpresaActiva::class);
        $router->aliasMiddleware('marketing.rol', RequiereRolEmpresa::class);
        $router->aliasMiddleware('marketing.principal', SoloEmpresaPrincipal::class);

        // Adaptador hacia el gateway ÚNICO de WhatsApp (item roadmap #203, Fase
        // A). Inerte por default (config('marketing_gateway.mode') = 'legacy');
        // ver App\Modules\Addons\Marketing\Listeners\GatewayMessageListener.
        Event::listen(WhatsAppTextReceived::class, [GatewayMessageListener::class, 'handleText']);
        Event::listen(WhatsAppMediaReceived::class, [GatewayMessageListener::class, 'handleMedia']);
    }
}
