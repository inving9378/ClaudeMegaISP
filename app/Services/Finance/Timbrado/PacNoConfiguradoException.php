<?php

namespace App\Services\Finance\Timbrado;

class PacNoConfiguradoException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'PAC no configurado. Integra un proveedor de timbrado (Facturama / Finkok / SW / Formas Digitales) ' .
            'implementando TimbradoServiceInterface antes de emitir facturas fiscales.'
        );
    }
}
