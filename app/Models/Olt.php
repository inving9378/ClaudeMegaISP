<?php

// Modelo movido a App\Modules\Addons\GestionRed\Models\Olt (roadmap #284).
// Alias de compatibilidad: los consumidores existentes siguen usando App\Models\Olt.

namespace App\Models;

class_alias(\App\Modules\Addons\GestionRed\Models\Olt::class, __NAMESPACE__ . '\\Olt');
