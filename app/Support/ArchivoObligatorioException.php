<?php

namespace App\Support;

use RuntimeException;

/** No se pudo guardar un archivo del que depende deshacer un cambio (ver ArchivoObligatorio). */
final class ArchivoObligatorioException extends RuntimeException
{
}
