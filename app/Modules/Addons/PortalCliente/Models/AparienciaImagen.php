<?php

namespace App\Modules\Addons\PortalCliente\Models;

use App\Models\BaseModel;

/**
 * Imagen subida desde Configuración → Portal Cliente. El archivo (ya re-codificado a WebP por
 * AparienciaImagenService) vive en el disco privado indicado en `disco`; aquí solo metadatos.
 */
class AparienciaImagen extends BaseModel
{
    protected $table = 'portal_cliente_apariencia_imagenes';

    protected $guarded = ['id'];

    protected $casts = [
        'peso'  => 'integer',
        'ancho' => 'integer',
        'alto'  => 'integer',
    ];
}
