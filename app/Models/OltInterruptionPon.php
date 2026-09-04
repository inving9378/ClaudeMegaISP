<?php

// Modelo movido a App\Modules\Addons\GestionRed\Models\OltInterruptionPon (roadmap #284).
// Alias de compatibilidad: los consumidores existentes siguen usando App\Models\OltInterruptionPon.

namespace App\Models;

class_alias(\App\Modules\Addons\GestionRed\Models\OltInterruptionPon::class, __NAMESPACE__ . '\\OltInterruptionPon');
