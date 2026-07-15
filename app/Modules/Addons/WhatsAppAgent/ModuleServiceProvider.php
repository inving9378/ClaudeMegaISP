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
