<?php

namespace App\Modules\Addons\PortalPago\Services\Cep;

/**
 * Se lanza cuando un reporte NO puede entrar a conciliación por un guard de
 * negocio (clave ya conciliada, factura ya pagada, liga ya conciliada). El
 * mensaje es apto para mostrarse al cliente/operador.
 */
class CepConciliationException extends \RuntimeException
{
}
