<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Guardar un archivo del que depende poder deshacer un cambio (respaldo, archivo para revertir).
 *
 * El disco local de Laravel no avisa cuando no puede escribir (`put()` devuelve false) y crea las
 * carpetas solo para su dueño (0700). Si una carpeta la creó otro usuario (la terminal frente al
 * servidor web), el cambio se hacía igual y el respaldo no quedaba (dev, 2026-10-22: una depuración
 * de 23,638 avisos sin archivo para revertir). Con esto, o el archivo queda o se lanza la excepción
 * y quien llama no escribe nada (o, si ya escribió, lo deja dicho en el log).
 */
final class ArchivoObligatorio
{
    /** @throws ArchivoObligatorioException */
    public static function guardar(string $ruta, string $contenido): void
    {
        $disco = Storage::disk('local');
        if (! $disco->put($ruta, $contenido) || ! $disco->exists($ruta)) {
            throw new ArchivoObligatorioException("No se pudo guardar storage/app/{$ruta}: el usuario del servidor no puede escribir en esa carpeta. No se cambió nada.");
        }
    }
}
