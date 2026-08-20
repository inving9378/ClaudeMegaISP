<?php

namespace App\Modules\Addons\Roadmap;

use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-roadmap';
    protected string $moduleType = 'addon';

    public function boot(): void
    {
        parent::boot();

        $this->vigilarProcesosProgramados();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\Addons\Roadmap\Console\RamaItemCommand::class,
                \App\Modules\Addons\Roadmap\Console\IntegrarItemCommand::class,
                \App\Modules\Addons\Roadmap\Console\FlagsCommand::class,
                \App\Modules\Addons\Roadmap\Console\RegistrarEjecucionCommand::class,
                \App\Modules\Addons\Roadmap\Console\VivoCommand::class,
                \App\Modules\Addons\Roadmap\Console\DisparoCheckCommand::class,
                // Aislamiento por worktree #334 Fase 0
                \App\Modules\Addons\Roadmap\Console\ProvisionWorktreeCommand::class,
                // Runner de merge (#334 F0-fix): merge on-box como meganet
                \App\Modules\Addons\Roadmap\Console\MergeRunCommand::class,
                // Paralelo #334 Fase 1
                \App\Modules\Addons\Roadmap\Console\SchedulerCommand::class,
                \App\Modules\Addons\Roadmap\Console\ClaimNextCommand::class,
                \App\Modules\Addons\Roadmap\Console\ReapStuckCommand::class,
                // Watchdog del equipo + auto-recuperación del supervisor (#334)
                \App\Modules\Addons\Roadmap\Console\WatchdogCommand::class,
                // Agente revisor #338
                \App\Modules\Addons\Roadmap\Console\RevisarItemCommand::class,
                \App\Modules\Addons\Roadmap\Console\RevisarBacklogCommand::class,
                \App\Modules\Addons\Roadmap\Console\RevisorFlagCommand::class,
                \App\Modules\Addons\Roadmap\Console\BriefCCommand::class,
                \App\Modules\Addons\Roadmap\Console\ProponerOpcionesCommand::class,
                \App\Modules\Addons\Roadmap\Console\DestrabeCommand::class,
                // Pasada de priorización por riesgo (seguridad/dinero → ALTA + brief) (#334)
                \App\Modules\Addons\Roadmap\Console\PriorizarSeguridadCommand::class,
                // Backfill de reporte_coloquial + regla en creación (#427)
                \App\Modules\Addons\Roadmap\Console\BackfillReporteColoquialCommand::class,
                // FASE 2A.3 — separa el freno humano del consejo del clasificador
                \App\Modules\Addons\Roadmap\Console\BackfillBloqueosCommand::class,
                \App\Modules\Addons\Roadmap\Console\CoherenciaPoolCommand::class,
                \App\Modules\Addons\Roadmap\Console\RetriageFrenosCommand::class,
                \App\Modules\Addons\Roadmap\Console\InventarioSpecCommand::class,
                // FASE 2A.3 — digest diario: prod tocada, decisiones mudas, dependencia del fallback
                \App\Modules\Addons\Roadmap\Console\DigestCommand::class,
                // Consejo asesor — piloto mínimo, 1 rol, manual (#344)
                \App\Modules\Addons\Roadmap\Console\AdvisorCobranzaCommand::class,
                // Autopilot: decide solo lo respaldado, deja a Irving lo indispensable (#507)
                \App\Modules\Addons\Roadmap\Console\AutopilotCommand::class,
                // Backfill de briefs de la bandeja para poblar confianza/reversible (#507)
                \App\Modules\Addons\Roadmap\Console\RebriefBandejaCommand::class,
                // TORRE V2 — Thomas (autoridad intermedia) y el kit de la terminal:
                // consultar en vez de despertar a Irving, reportar sin pisar, y partir en sub-items.
                \App\Modules\Addons\Roadmap\Console\ThomasCommand::class,
                \App\Modules\Addons\Roadmap\Console\ConsultarSupervisorCommand::class,
                \App\Modules\Addons\Roadmap\Console\ReportarItemCommand::class,
                \App\Modules\Addons\Roadmap\Console\SubItemCommand::class,
                // #566 — footprint a los "Sin clasificar": sin él cada uno serializa la flota.
                \App\Modules\Addons\Roadmap\Console\ClasificarModuloCommand::class,
                // #566 — re-triaje de la bandeja con el carril mecánico
                \App\Modules\Addons\Roadmap\Console\RetriarBandejaCommand::class,
                // #566 — destrabe: enruta cada item a lo que ESPERA de verdad (merge/decisión/consolidado)
                \App\Modules\Addons\Roadmap\Console\DestrabarCommand::class,
                // #559 — MOTOR DE AUDITORÍA CONTINUA: el generador de trabajo. Cierra el hueco que
                // quedaba (repartir y juzgar ya existían; generar, no), para que la cola no se vacíe.
                \App\Modules\Addons\Roadmap\Console\AuditorCommand::class,
            ]);
        }
    }

    /**
     * FASE 2A.7 (#808) — cada proceso programado sella su último latido AL TERMINAR BIEN.
     *
     * Un SOLO listener para todos, en vez de instrumentar comando por comando: así un proceso nuevo
     * sólo necesita su fila en `config('circuito.procesos_programados')` y no existe el escenario de
     * "se agregó el cron y se olvidó el latido". La lista de qué se vigila vive en la config, que es
     * también donde se declara QUÉ SE PIERDE si deja de correr.
     *
     * Sólo cuenta la salida 0: un comando que aborta no es un proceso que corrió.
     */
    private function vigilarProcesosProgramados(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Console\Events\CommandFinished::class,
            function (\Illuminate\Console\Events\CommandFinished $e) {
                if (! $e->command) {
                    return;
                }
                try {
                    $svc = app(\App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService::class);

                    if ($e->exitCode === 0) {
                        $svc->sellarLatido($e->command, $e->input);
                    } else {
                        // Salida != 0 es un FALLO, y se registra con su código. Sin esto, un motor
                        // que se cae en cada intento pero logró una corrida buena hace rato se ve
                        // sano: el latido está fresco y nada más lo contradice.
                        $svc->sellarFallo($e->command, "exit code {$e->exitCode}");
                    }
                } catch (\Throwable) {
                    // El registro jamás puede tumbar al comando que acaba de correr.
                }
            }
        );
    }
}
