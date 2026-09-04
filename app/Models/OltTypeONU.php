<?php

// Modelo movido a App\Modules\Addons\GestionRed\Models\OltTypeONU (roadmap #284).
// Alias de compatibilidad: los consumidores existentes siguen usando App\Models\OltTypeONU.

namespace App\Models;

class_alias(\App\Modules\Addons\GestionRed\Models\OltTypeONU::class, __NAMESPACE__ . '\\OltTypeONU');
