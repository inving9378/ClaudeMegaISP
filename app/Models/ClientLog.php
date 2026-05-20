<?php

namespace App\Models;

/**
 * Proxy de backward-compatibility — el modelo real vive ahora en
 * \App\Modules\Core\Clientes\Models\ClientLog desde la migración modular
 * (Capa 1/6). Mantener mientras existan imports legacy en el codebase.
 */
class ClientLog extends \App\Modules\Core\Clientes\Models\ClientLog
{
}
