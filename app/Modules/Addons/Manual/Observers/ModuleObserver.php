<?php

namespace App\Modules\Addons\Manual\Observers;

use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Observa cambios en `module_registry`. Cuando un módulo pasa de
 * inactivo a activo, regenera SU sección del manual.
 */
class ModuleObserver
{
    public function updated(ModuleRegistry $module): void
    {
        if (!$module->wasChanged('active')) {
            return;
        }
        $previous = $module->getOriginal('active');
        if ((bool) $previous === true || (bool) $module->active !== true) {
            return;
        }

        $slug = (string) $module->slug;
        Log::info("[Manual] Módulo activado '{$slug}' — disparando manual:regenerate --section={$slug}");

        try {
            Artisan::call('manual:regenerate', ['--section' => $slug]);
            Log::info('[Manual] Regeneración por activación completada', [
                'slug'   => $slug,
                'output' => trim(Artisan::output()),
            ]);
        } catch (\Throwable $e) {
            Log::error('[Manual] Falló regeneración por activación: ' . $e->getMessage(), [
                'slug' => $slug,
            ]);
        }
    }
}
