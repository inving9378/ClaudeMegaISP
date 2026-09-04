<?php

// Modelo movido a App\Modules\Addons\GestionRed\Models\OltVlan (roadmap #284).
// Alias de compatibilidad: los consumidores existentes siguen usando App\Models\OltVlan.

namespace App\Models;

class_alias(\App\Modules\Addons\GestionRed\Models\OltVlan::class, __NAMESPACE__ . '\\OltVlan');
