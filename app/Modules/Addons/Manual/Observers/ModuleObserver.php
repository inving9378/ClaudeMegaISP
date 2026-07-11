<?php

namespace App\Modules\Addons\Manual\Observers;

use App\Modules\Addons\Manual\Jobs\RegenerateManualSectionJob;
use App\Modules\Addons\Manual\Models\ManualSection;
use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
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

        // Guard anti-gasto Claude API (item #107): manual:regenerate llama a Claude
        // y versiona la sección. Solo auto-generamos la PRIMERA vez que el módulo se
        // activa; si ya existe manual para este slug (p. ej. re-activación/toggle),
        // se omite para no re-gastar tokens. El admin puede forzar regen manual.
        if (ManualSection::where('module_slug', $slug)->exists()) {
            Log::info("[Manual] '{$slug}' ya tiene manual — se omite regeneración automática (re-activación).");
            return;
        }

        Log::info("[Manual] Módulo activado '{$slug}' — encolando RegenerateManualSectionJob (section={$slug})");

        RegenerateManualSectionJob::dispatch($slug);
    }
}
