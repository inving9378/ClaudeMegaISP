<?php

namespace App\Http\Controllers\Module\Client;

/**
 * Proxy de backward-compatibility — el controller real vive ahora en
 * \App\Modules\Core\Clientes\Controllers\DashboardController (Capa 4/6).
 * Las rutas en routes/web.php (namespace => 'Client') resuelven a esta
 * proxy class que hereda todos los métodos del controller real.
 */
class DashboardController extends \App\Modules\Core\Clientes\Controllers\DashboardController
{
}
