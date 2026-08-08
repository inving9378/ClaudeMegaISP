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
                // #559 — MOTOR DE AUDITORÍA CONTINUA: el generador de trabajo. Cierra el hueco que
                // quedaba (repartir y juzgar ya existían; generar, no), para que la cola no se vacíe.
                \App\Modules\Addons\Roadmap\Console\AuditorCommand::class,
            ]);
        }
    }
}
