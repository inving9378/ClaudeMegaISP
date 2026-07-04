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

    // Nota: los observers backstop de la capa de funciones (Fase 3) se ELIMINARON al
    // relajar la regla (una función puede quedar sin línea, con aviso en la UI). No hay
    // boot() propio; BaseModuleServiceProvider::boot() (migraciones/vistas/rutas) se hereda.
}
