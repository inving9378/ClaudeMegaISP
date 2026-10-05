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

        $this->mergeConfigFrom(
            base_path('config/whatsapp_templates.php'),
            'whatsapp_templates'
        );

        // Item: migración a la API oficial de Meta, configurable. Los
        // consumidores que reciben EvolutionApiService por inyección de
        // dependencias (en vez de `new`) deben tipar contra esta interfaz en
        // vez de la clase concreta -- así Laravel resuelve el proveedor
        // ACTIVO (Evolution u oficial de Meta) automáticamente, sin que cada
        // job tenga que llamar a la fábrica a mano. Sin companyId explícito
        // aquí (el binding no lo conoce) -- cae a la línea default de la
        // company 1; los consumidores con companyId propio (multi-tenant)
        // siguen llamando WhatsAppProviderFactory::forDefault($companyId)
        // directo, como ya hacían con `new EvolutionApiService($companyId)`.
        $this->app->bind(
            \App\Modules\Addons\WhatsAppAgent\Contracts\WhatsAppProviderInterface::class,
            fn () => \App\Modules\Addons\WhatsAppAgent\Services\WhatsAppProviderFactory::forDefault()
        );
    }

    public function boot(): void
    {
        parent::boot();

        // Gateway → consumidores (listeners). Cada módulo se suscribe AQUÍ, sin
        // tocar el gateway. Fase 1: IA para texto. La conciliación (media) se
        // suma en su propio sub-paso.
        \Illuminate\Support\Facades\Event::listen(
            \App\Modules\Addons\WhatsAppAgent\Events\WhatsAppTextReceived::class,
            \App\Modules\Addons\WhatsAppAgent\Listeners\IaAutoReplyListener::class,
        );

        // Agente de Ventas (IA) — aislado, gateado por su propia función
        // 'agente_ventas' (ver seed 2026_09_28_100000). Cero código
        // compartido con IaAutoReplyListener de arriba.
        \Illuminate\Support\Facades\Event::listen(
            \App\Modules\Addons\WhatsAppAgent\Events\WhatsAppTextReceived::class,
            \App\Modules\Addons\WhatsAppAgent\Listeners\AgenteVentasTextListener::class,
        );

        // Consumidor de un evento de dominio SALIENTE de otro módulo (item #253):
        // Embajadores publica la intención de compartir, este listener la traduce
        // al gateway. Embajadores no conoce Evolution ni WhatsAppGateway.
        \Illuminate\Support\Facades\Event::listen(
            \App\Modules\Addons\Embajadores\Events\ReferralShareRequested::class,
            \App\Modules\Addons\WhatsAppAgent\Listeners\SendReferralShareListener::class,
        );
    }

    // Nota: los observers backstop de la capa de funciones (Fase 3) se ELIMINARON al
    // relajar la regla (una función puede quedar sin línea, con aviso en la UI). No hay
    // boot() propio; BaseModuleServiceProvider::boot() (migraciones/vistas/rutas) se hereda.
}
