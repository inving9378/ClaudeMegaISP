<?php

namespace App\Modules\Addons\Talento;

use App\Models\PaymentByRuleDetails;
use App\Modules\Addons\Talento\Console\CheckCredentialExpirationsCommand;
use App\Modules\Addons\Talento\Console\ConciliarComisionesVendedorCommand;
use App\Modules\Addons\Talento\Console\ReconciliarComisionesEspejoCommand;
use App\Modules\Addons\Talento\Console\SyncColaboradoresCommand;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Observers\TalentoColaboradorObserver;
use App\Modules\Addons\Talento\Observers\TalentoComisionEspejoObserver;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-talento';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-talento';

    public function boot(): void
    {
        parent::boot();

        // Auto-asignación del permiso base del Portal de Colaborador según el estado del colaborador.
        TalentoColaborador::observe(TalentoColaboradorObserver::class);

        // Fase 1b (#9990610) del puente Vendedores→Talento: espejo de comisiones reales,
        // inerte mientras config('talento.vendedores_espejo_enabled') sea false.
        PaymentByRuleDetails::observe(TalentoComisionEspejoObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckCredentialExpirationsCommand::class,
                SyncColaboradoresCommand::class,
                ConciliarComisionesVendedorCommand::class,
                ReconciliarComisionesEspejoCommand::class,
            ]);
        }
    }
}
