<?php

namespace App\Services\ClientService;

/**
 * Proxy de backward-compatibility — la interface real vive ahora en
 * \App\Modules\Core\Clientes\Services\ClientServiceInterface (Capa 3/6).
 * Implementadores de esta interface heredan transitivamente la nueva
 * vía interface extends.
 */
interface ClientServiceInterface extends \App\Modules\Core\Clientes\Services\ClientServiceInterface
{
}
