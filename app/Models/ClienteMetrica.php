<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un minuto de un cliente dedicado (item #988): su tráfico en la perspectiva del cliente
 * (bajada = tx de su interfaz VLAN, subida = rx) y el resumen del ping a sus IPs. Model plano
 * (sin BaseModel ni log de actividad): una fila por cliente por minuto.
 */
class ClienteMetrica extends Model
{
    protected $table = 'cliente_metricas';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'ts'             => 'datetime',
        'bajada_bps'     => 'integer',
        'subida_bps'     => 'integer',
        'bajada_pps'     => 'integer',
        'subida_pps'     => 'integer',
        'ping_ips'       => 'integer',
        'ping_responden' => 'integer',
        'lat_avg_ms'     => 'float',
        'lat_max_ms'     => 'float',
        'loss_pct'       => 'float',
    ];

    public const ORIGEN_MIKROTIK = 'mikrotik';
    public const ORIGEN_SIMULADO = 'simulado';

    public const PING_EN_LINEA = 'en_linea';
    public const PING_DEGRADADO = 'degradado';
    public const PING_SIN_RESPUESTA = 'sin_respuesta';
}
