<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalCliente\Models\AparienciaImagen;
use App\Modules\Addons\PortalCliente\Services\AparienciaImagenService;
use App\Modules\Addons\PortalCliente\Services\AparienciaService;
use Illuminate\Support\Str;

/**
 * Imágenes de las pantallas de acceso para el público. Solo sirve las que usa la versión
 * PUBLICADA: una imagen subida a un borrador (o de una versión vieja) responde 404 aquí.
 */
class AparienciaImagenController extends Controller
{
    public function mostrar(string $uuid, AparienciaService $apariencia, AparienciaImagenService $imagenes)
    {
        abort_unless(Str::isUuid($uuid), 404);
        abort_unless(in_array($uuid, $apariencia->uuidsDe($apariencia->configPublicada()), true), 404);

        return $imagenes->respuesta(AparienciaImagen::where('uuid', $uuid)->firstOrFail(), true);
    }
}
