<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Última lectura cruda de los contadores de la interfaz VLAN de un servicio dedicado (item #988).
 * Aquí los nombres son los del router (rx/tx); la conversión a bajada/subida la hace el
 * colector al calcular la diferencia.
 */
class ClienteMetricaContador extends Model
{
    protected $table = 'cliente_metricas_contadores';

    protected $primaryKey = 'servicio_id';

    public $incrementing = false;

    public $timestamps = false;

    // leido_at va con milisegundos (datetime(3)) y se lee crudo: el cast 'datetime' espera el
    // formato sin fracción.
    protected $guarded = [];
}
