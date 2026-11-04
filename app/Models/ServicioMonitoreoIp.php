<?php

namespace App\Models;

/**
 * IP del bloque de un servicio dedicado a la que se hace ping desde su MikroTik (item #988).
 * Siempre dentro del bloque y nunca la IP del router (#999).
 */
class ServicioMonitoreoIp extends BaseModel
{
    protected $table = 'servicio_monitoreo_ips';

    protected $guarded = [];

    protected $casts = [
        'monitorear' => 'boolean',
        'orden'      => 'integer',
    ];
}
