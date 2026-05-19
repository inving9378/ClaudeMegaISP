<?php

namespace App\Services\ClientService;

/**
 * Proxy de backward-compatibility — el service real vive ahora en
 * \App\Modules\Core\Clientes\Services\ClientBillingService (Capa 3/6).
 */
class ClientBillingService extends \App\Modules\Core\Clientes\Services\ClientBillingService
{
}
