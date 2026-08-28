<?php

// Modelo movido a App\Modules\Addons\GestionRed\Models\OltBilling (roadmap #284).
// Alias de compatibilidad: los consumidores existentes siguen usando App\Models\OltBilling.

namespace App\Models;

class_alias(\App\Modules\Addons\GestionRed\Models\OltBilling::class, __NAMESPACE__ . '\\OltBilling');
