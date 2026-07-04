<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppFunction;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;

/**
 * Lectura pura: ¿qué línea atiende una función? Puente para que Fase 4c (conciliación/bot/
 * cobranza) resuelvan la línea por función en vez de leer la config de Marketing.
 *
 * ⚠️ Fase 3: se construye pero NADIE lo consume aún. NO toca el sender ni Marketing.
 */
class WhatsAppLineResolver
{
    /**
     * Devuelve la línea (activa) que atiende la función con ese slug, o null si no hay
     * función activa o no tiene línea asignada. Para funciones exclusivas hay a lo más una;
     * para no exclusivas devuelve la primera activa (Fase 4c definirá la política de reparto).
     */
    public function lineForFunction(string $slug): ?WhatsAppInstance
    {
        $function = WhatsAppFunction::where('slug', $slug)->active()->first();
        if (! $function) {
            return null;
        }

        return $function->instances()->where('active', true)->orderBy('whatsapp_instances.id')->first();
    }

    /**
     * Todas las líneas activas que atienden la función (útil para funciones no exclusivas).
     *
     * @return \Illuminate\Support\Collection<int,WhatsAppInstance>
     */
    public function linesForFunction(string $slug): \Illuminate\Support\Collection
    {
        $function = WhatsAppFunction::where('slug', $slug)->active()->first();
        if (! $function) {
            return collect();
        }

        return $function->instances()->where('active', true)->get();
    }
}
