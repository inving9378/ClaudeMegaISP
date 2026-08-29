<?php

namespace App\Modules\Addons\DocumentacionCorporativa;

use App\Modules\Addons\DocumentacionCorporativa\Contracts\FuenteRegistry;
use App\Modules\Addons\DocumentacionCorporativa\Fuentes\FinanzasFuentes;
use App\Modules\Addons\DocumentacionCorporativa\Fuentes\TalentoFuentes;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-documentacion-corporativa';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-documentacion-corporativa';

    public function register(): void
    {
        parent::register();

        // SINGLETON, y no es un detalle: `SistemaResolver`/`GraficaResolver` reciben
        // el registro por inyección. Sin singleton cada uno recibiría su propia
        // instancia VACÍA y las fuentes que registre cualquier fase posterior no
        // llegarían nunca al resolvedor — el concepto seguiría diciendo "sin fuente
        // configurada" para siempre, sin ningún error que lo delatara.
        $this->app->singleton(FuenteRegistry::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Fase 1.1 (item #728): fuentes vivas de finanzas del Apartado IV.
        // Cada fase posterior agrega su propio `Fuentes\*::registrar()` aquí.
        FinanzasFuentes::registrar($this->app->make(FuenteRegistry::class));

        // Fase 1.3 (item #730): fuentes vivas de talento humano del Apartado VII.
        TalentoFuentes::registrar($this->app->make(FuenteRegistry::class));
    }
}
