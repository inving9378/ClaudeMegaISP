<?php

// Modelo movido a App\Modules\Addons\GestionRed\Models\OltUnconfiguredOnu (roadmap #284).
// Alias de compatibilidad: los consumidores existentes siguen usando App\Models\OltUnconfiguredOnu.

namespace App\Models;

class_alias(\App\Modules\Addons\GestionRed\Models\OltUnconfiguredOnu::class, __NAMESPACE__ . '\\OltUnconfiguredOnu');
