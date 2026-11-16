<?php

namespace App\Modules\Addons\LogsTerceros\Services;

/**
 * La contraseña para leer como root (de root con su, o del usuario con sudo) no sirvió o faltó.
 * `falta`: no se escribió ninguna; no cuenta como intento equivocado.
 */
class ContrasenaRootIncorrecta extends \RuntimeException
{
    public bool $falta = false;

    public static function falta(string $mensaje): self
    {
        $e = new self($mensaje);
        $e->falta = true;

        return $e;
    }
}
