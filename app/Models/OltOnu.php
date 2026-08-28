<?php

// Modelo movido a App\Modules\Addons\GestionRed\Models\OltOnu (roadmap #284).
// Alias de compatibilidad: los consumidores existentes siguen usando App\Models\OltOnu.

namespace App\Models;

class_alias(\App\Modules\Addons\GestionRed\Models\OltOnu::class, __NAMESPACE__ . '\\OltOnu');
