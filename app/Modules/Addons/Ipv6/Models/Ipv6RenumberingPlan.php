<?php

namespace App\Modules\Addons\Ipv6\Models;

use App\Models\BaseModel;

// BaseModel (no plano): la tabla SÍ tiene created_by/updated_by (auto-stamp).
class Ipv6RenumberingPlan extends BaseModel
{
    protected $table = 'ipv6_renumbering_plans';

    protected $fillable = [
        'bloque_viejo',
        'bloque_nuevo',
        'estado',
        'valid_lifetime_segundos',
        'preferred_lifetime_segundos',
        'deprecacion_inicia_at',
        'retiro_programado_at',
        'morosos_reconstruido_at',
        'historico_preservado',
        'liberado_en',
        'simple_queues_actualizado_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'valid_lifetime_segundos' => 'integer',
        'preferred_lifetime_segundos' => 'integer',
        'deprecacion_inicia_at' => 'datetime',
        'retiro_programado_at' => 'datetime',
        'morosos_reconstruido_at' => 'datetime',
        'historico_preservado' => 'boolean',
        'liberado_en' => 'datetime',
        'simple_queues_actualizado_at' => 'datetime',
    ];

    public function transitions()
    {
        return $this->hasMany(Ipv6RenumberingTransition::class, 'plan_id');
    }
}
