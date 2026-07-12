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
                // Agente revisor #338
                \App\Modules\Addons\Roadmap\Console\RevisarItemCommand::class,
                \App\Modules\Addons\Roadmap\Console\RevisarBacklogCommand::class,
                \App\Modules\Addons\Roadmap\Console\RevisorFlagCommand::class,
                \App\Modules\Addons\Roadmap\Console\BriefCCommand::class,
                \App\Modules\Addons\Roadmap\Console\DestrabeCommand::class,
            ]);
        }
    }
}
