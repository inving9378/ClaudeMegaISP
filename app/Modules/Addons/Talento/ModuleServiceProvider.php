<?php

namespace App\Modules\Addons\Talento;

use App\Models\PaymentByRuleDetails;
use App\Modules\Addons\Talento\Console\CheckCredentialExpirationsCommand;
use App\Modules\Addons\Talento\Console\ConciliarComisionesVendedorCommand;
use App\Modules\Addons\Talento\Console\CerrarEncuestasCommand;
use App\Modules\Addons\Talento\Console\EvaluarReglasSemanalesCommand;
use App\Modules\Addons\Talento\Console\ReconciliarComisionesEspejoCommand;
use App\Modules\Addons\Talento\Console\RegenerarDocumentosCommand;
use App\Modules\Addons\Talento\Console\SimularAislamientoReglasCommand;
use App\Modules\Addons\Talento\Console\SyncColaboradoresCommand;
use App\Modules\Addons\Talento\Console\NormalizarDepartamentosCommand;
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

        // UNI-T B6 (#875): middleware de cuenta activa + revocar tokens al inactivar users.estado.
        $this->app['router']->aliasMiddleware('talento.cuenta_activa', \App\Modules\Addons\Talento\Middleware\CuentaActivaMiddleware::class);
        \App\Models\User::updated(function (\App\Models\User $user) {
            if (config('unificacion.interruptores.cuenta_activa') && $user->wasChanged('estado') && $user->isNotActive()) {
                $user->tokens()->delete();
            }
        });

        // UNI-T L1 (#927): un ticket nuevo dentro de la ventana de garantía queda ligado a la orden original.
        \App\Models\Ticket::created(function (\App\Models\Ticket $ticket) {
            if (! config('unificacion.interruptores.garantia_l1') || ! $ticket->customer_lead) {
                return;
            }
            try {
                app(\App\Modules\Addons\Talento\Services\WarrantyWindowService::class)
                    ->registrarEventoTicket($ticket->id, (int) $ticket->customer_lead);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Warranty event failed', ['ticket_id' => $ticket->id, 'err' => $e->getMessage()]);
            }
        });

        // Fase 1b (#9990610) del puente Vendedores→Talento: espejo de comisiones reales,
        // inerte mientras config('talento.vendedores_espejo_enabled') sea false.
        PaymentByRuleDetails::observe(TalentoComisionEspejoObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckCredentialExpirationsCommand::class,
                SyncColaboradoresCommand::class,
                NormalizarDepartamentosCommand::class,
                \App\Modules\Addons\Talento\Console\MarcarOrdenesPruebaCommand::class,
                ConciliarComisionesVendedorCommand::class,
                ReconciliarComisionesEspejoCommand::class,
                RegenerarDocumentosCommand::class,
                EvaluarReglasSemanalesCommand::class,
                SimularAislamientoReglasCommand::class,
                CerrarEncuestasCommand::class,
            ]);
        }
    }
}
