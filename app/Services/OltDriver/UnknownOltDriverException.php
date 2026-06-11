<?php

namespace App\Services\OltDriver;

use RuntimeException;

class UnknownOltDriverException extends RuntimeException
{
    public function __construct(string $driver)
    {
        parent::__construct(
            "Driver OLT desconocido: '{$driver}'. Valores válidos: 'smartolt', 'huawei'."
        );
    }
}
