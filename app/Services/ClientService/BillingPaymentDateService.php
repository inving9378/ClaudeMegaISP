<?php

namespace App\Services\ClientService;

/**
 * Proxy de backward-compatibility — el service real vive ahora en
 * \App\Modules\Core\Clientes\Services\BillingPaymentDateService (Capa 3/6).
 */
class BillingPaymentDateService extends \App\Modules\Core\Clientes\Services\BillingPaymentDateService
{
}
