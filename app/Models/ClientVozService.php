<?php

namespace App\Models;

/**
 * Proxy de backward-compatibility — el modelo real vive ahora en
 * \App\Modules\Core\Clientes\Models\ClientVozService desde la migración modular
 * (Capa 1/6). Mantener mientras existan imports legacy en el codebase.
 */
class ClientVozService extends \App\Modules\Core\Clientes\Models\ClientVozService
{
}
