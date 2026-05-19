<?php

namespace App\Http\Repository;

/**
 * Proxy de backward-compatibility — el repositorio real vive ahora en
 * \App\Modules\Core\Clientes\Repositories\ClientAdditionalInformationRepository desde la migración modular
 * (Capa 2/6). Mantener mientras existan imports legacy en el codebase.
 */
class ClientAdditionalInformationRepository extends \App\Modules\Core\Clientes\Repositories\ClientAdditionalInformationRepository
{
}
