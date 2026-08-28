<?php

// Modelo movido a App\Modules\Addons\GestionRed\Models\OltPonPort (roadmap #284).
// Alias de compatibilidad: los consumidores existentes siguen usando App\Models\OltPonPort.

namespace App\Models;

class_alias(\App\Modules\Addons\GestionRed\Models\OltPonPort::class, __NAMESPACE__ . '\\OltPonPort');
