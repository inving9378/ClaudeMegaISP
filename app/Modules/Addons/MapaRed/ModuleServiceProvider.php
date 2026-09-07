<?php

namespace App\Modules\Addons\MapaRed;

use App\Modules\Addons\MapaRed\Console\BackfillCommand;
use App\Modules\Addons\MapaRed\Console\ImportarLegacyCommand;
use App\Modules\Addons\MapaRed\Console\ValidarPresupuestoOpticoCommand;
use App\Modules\Addons\MapaRed\Models\MapaRedEmpalme;
use App\Modules\Addons\MapaRed\Models\MapaRedHistorial;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
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

        // MR-23 fase 4d (#9990456) — historial de cambios de nodos/enlaces (MapaRedLayer,
        // el modelo real que edita ElementSidePanel.vue vía LayersController). Un renglón
        // por campo de negocio cambiado; ver MapaRedHistorial::CAMPOS_RELEVANTES_LAYER.
        MapaRedLayer::created(function (MapaRedLayer $layer) {
            MapaRedHistorial::registrarCreacion(MapaRedLayer::class, $layer->getKey());
        });
        MapaRedLayer::updated(function (MapaRedLayer $layer) {
            foreach (MapaRedHistorial::CAMPOS_RELEVANTES_LAYER as $campo) {
                if (!$layer->wasChanged($campo)) {
                    continue;
                }
                MapaRedHistorial::registrarEdicion(
                    MapaRedLayer::class,
                    $layer->getKey(),
                    $campo,
                    $layer->getOriginal($campo),
                    $layer->getAttribute($campo)
                );
            }
        });
        MapaRedLayer::deleted(function (MapaRedLayer $layer) {
            MapaRedHistorial::registrarEliminacion(MapaRedLayer::class, $layer->getKey());
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                BackfillCommand::class,
                ImportarLegacyCommand::class,
                ValidarPresupuestoOpticoCommand::class,
            ]);
        }
    }
}
