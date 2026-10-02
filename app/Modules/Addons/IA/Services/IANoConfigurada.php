<?php

namespace App\Modules\Addons\IA\Services;

use RuntimeException;

/**
 * El módulo no tiene una IA utilizable asignada en Integraciones → Módulos IA
 * (sin asignación, integración inactiva/sin llave, o proveedor sin la capacidad
 * que el módulo necesita). El mensaje es apto para mostrarse al usuario.
 */
class IANoConfigurada extends RuntimeException
{
}
