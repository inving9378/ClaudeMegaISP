<?php

namespace App\Modules\Addons\WhatsAppAgent\Observers;

use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppFunctionService;

/**
 * Backstop de la regla 6: al borrar una línea (incluye el destroy() del controller),
 * bloquea si la línea es la única dueña de alguna función. El guard lanza
 * WhatsAppFunctionException (render 422) → la operación se aborta antes del DELETE.
 */
class WhatsAppInstanceObserver
{
    public function deleting(WhatsAppInstance $instance): void
    {
        app(WhatsAppFunctionService::class)->guardInstanceRemoval($instance);
    }
}
