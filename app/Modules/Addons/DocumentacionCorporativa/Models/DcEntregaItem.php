<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una línea del índice de una entrega: qué concepto entró, con qué nivel de
 * detalle y qué archivo del ZIP le corresponde. Sin soft delete: se genera y
 * se lee, es parte del acuse — igual espíritu que `dc_accesos_log`.
 */
class DcEntregaItem extends Model
{
    protected $table = 'dc_entrega_items';

    public const UPDATED_AT = null;

    protected $fillable = [
        'entrega_id', 'apartado_clave', 'concepto_id', 'nivel_detalle',
        'archivo_incluido', 'estado_resuelto', 'metricas',
    ];

    protected $casts = [
        'metricas' => 'array',
    ];

    public const NIVELES = ['agregado', 'detallado', 'integro'];

    public function entrega()
    {
        return $this->belongsTo(DcEntrega::class, 'entrega_id');
    }

    public function concepto()
    {
        return $this->belongsTo(DcConcepto::class, 'concepto_id');
    }
}
