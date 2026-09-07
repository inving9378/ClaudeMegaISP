<?php

namespace App\Modules\Addons\MapaRed;

use App\Modules\Addons\MapaRed\Console\BackfillCommand;
use App\Modules\Addons\MapaRed\Console\ImportarLegacyCommand;
use App\Modules\Addons\MapaRed\Console\ValidarPresupuestoOpticoCommand;
use App\Modules\Addons\MapaRed\Models\MapaRedEmpalme;
use App\Modules\Addons\MapaRed\Services\RedGraphService;
use App\Modules\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleSlug = 'addon-mapa-red';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = 'addon-mapa-red';

    public function boot(): void
    {
        parent::boot();

        // MR-16 Fase 1 (#9990468) — invalidación EXPLÍCITA del caché de RedGraphService al
        // editar un empalme (crear/actualizar/borrar/restaurar). No hay hoy ningún controller
        // que mute MapaRedEmpalme (grep confirmado: cero); enganchar al modelo cubre a
        // cualquier futuro punto de escritura sin depender de que se acuerden de invalidar.
        MapaRedEmpalme::saved(fn () => RedGraphService::invalidarCache());
        MapaRedEmpalme::deleted(fn () => RedGraphService::invalidarCache());
        MapaRedEmpalme::restored(fn () => RedGraphService::invalidarCache());

        if ($this->app->runningInConsole()) {
            $this->commands([
                BackfillCommand::class,
                ImportarLegacyCommand::class,
                ValidarPresupuestoOpticoCommand::class,
            ]);
        }
    }
}
