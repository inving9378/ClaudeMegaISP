<?php

namespace App\Support\Red;

use RuntimeException;

/** Un cambio a un router u OLT que no salió porque la red está en solo lectura (config/red.php). */
class RedSoloLecturaException extends RuntimeException
{
}
