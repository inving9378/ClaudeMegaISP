<?php

namespace App\Modules\Addons\WhatsAppAgent\Observers;

use App\Modules\Addons\WhatsAppAgent\Exceptions\WhatsAppFunctionException;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstanceFunction;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppFunctionService;

/**
 * Backstop de la regla 5: al borrar una fila del pivote, si es la ÚLTIMA asignación de
 * esa función → aborta (no la deja huérfana). Excepción: durante un move/reassign
 * (bandera $reassigning) sí se permite, porque enseguida se re-attacha en otra línea.
 */
class WhatsAppInstanceFunctionObserver
{
    public function deleting(WhatsAppInstanceFunction $row): void
    {
        if (WhatsAppFunctionService::$reassigning) {
            return;
        }

        $owners = WhatsAppInstanceFunction::where('function_id', $row->function_id)->count();
        if ($owners <= 1 && $row->function) {
            throw WhatsAppFunctionException::wouldOrphan($row->function);
        }
    }
}
