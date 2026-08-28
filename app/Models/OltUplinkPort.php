<?php

// Modelo movido a App\Modules\Addons\GestionRed\Models\OltUplinkPort (roadmap #284).
// Alias de compatibilidad: los consumidores existentes siguen usando App\Models\OltUplinkPort.

namespace App\Models;

class_alias(\App\Modules\Addons\GestionRed\Models\OltUplinkPort::class, __NAMESPACE__ . '\\OltUplinkPort');
