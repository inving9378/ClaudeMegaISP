<?php

namespace App\Modules\Core\Layout\ViewComposers;

use App\Models\Release;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TopbarComposer
{
    /**
     * Badge "PRODUCCIÓN · {versión}" del topbar.
     *
     * Visibilidad: SOLO en instancias consumidoras (auto-update activo). La señal es
     * config('updates.enabled') — la misma que gobierna el polling de GitHub y el cron
     * remote:deploy-run-pending. Fail-safe: default false → en dev/publicador no aparece.
     *
     * Versión instalada = última fila de `releases` (misma fuente que el comparador del
     * banner, GitHubUpdateService). Cacheada permanentemente; el deploy remoto invalida
     * la clave 'megaisp_installed_version' en el paso save_release (único momento en que
     * la versión instalada cambia).
     */
    public function compose(View $view): void
    {
        $isProduction = (bool) config('updates.enabled');

        $installedVersion = $isProduction
            ? Cache::rememberForever(
                'megaisp_installed_version',
                fn () => optional(Release::latest('release_date')->latest('id')->first())->version
            )
            : null;

        $view->with('showProductionBadge', $isProduction && !empty($installedVersion));
        $view->with('installedVersion', $installedVersion);
    }
}
