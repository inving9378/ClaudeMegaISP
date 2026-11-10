<?php

namespace App\Support\Red;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Request;

/**
 * Cliente RouterOS con el candado de solo lectura (config/red.php). sendSync, sendAsync y los
 * ayudantes de Util terminan todos en send(), así que basta revisar aquí: con el candado puesto,
 * lo que no sea consulta no sale hacia el router. Sin candado se comporta igual que el original.
 * El inicio de sesión no pasa por send() y no se ve afectado.
 */
class ClienteRouterOs extends Client
{
    protected function send(Request $request)
    {
        $comando = $request->getCommand();
        if (SoloLectura::activo() && ! SoloLectura::permiteRouterOs($comando)) {
            throw SoloLectura::bloqueo('MikroTik', $comando);
        }

        return parent::send($request);
    }
}
