<?php

namespace App\Modules\Addons\Ipv6\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Model plano (NO BaseModel): ipv6_plan_segmentos no tiene created_by/updated_by.
class Ipv6PlanSegmento extends Model
{
    protected $table = 'ipv6_plan_segmentos';

    protected $fillable = [
        'bloque_id',
        'router_id',
        'tipo',
        'nombre',
        'prefijo',
        'longitud_delegacion',
        'vlan_id',
        'interfaz_mikrotik',
        'perfil_ppp',
        'ipv6_habilitado',
    ];

    protected $casts = [
        'ipv6_habilitado' => 'boolean',
    ];

    public function bloque()
    {
        return $this->belongsTo(Ipv6Bloque::class, 'bloque_id');
    }

    public function router()
    {
        return $this->belongsTo(Ipv6Router::class, 'router_id');
    }

    public function scopePorBloque(Builder $q, int $bloqueId): Builder
    {
        return $q->where('bloque_id', $bloqueId);
    }
}
