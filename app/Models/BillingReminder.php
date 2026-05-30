<?php

namespace App\Models;

/**
 * Alias para compatibilidad con la tabla `modules` (is_main=1).
 * El modelo real vive en App\Modules\Core\Configuracion\Models\BillingReminder.
 */
class BillingReminder extends \App\Modules\Core\Configuracion\Models\BillingReminder
{
}
