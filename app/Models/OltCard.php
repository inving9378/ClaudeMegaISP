<?php

// Modelo movido a App\Modules\Addons\GestionRed\Models\OltCard (roadmap #284).
// Alias de compatibilidad: los consumidores existentes siguen usando App\Models\OltCard.

namespace App\Models;

class_alias(\App\Modules\Addons\GestionRed\Models\OltCard::class, __NAMESPACE__ . '\\OltCard');
