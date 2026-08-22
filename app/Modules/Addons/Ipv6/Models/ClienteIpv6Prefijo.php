<?php

namespace App\Modules\Addons\Ipv6\Models;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Model plano (NO BaseModel): clientes_ipv6_prefijos no tiene created_by/updated_by.
class ClienteIpv6Prefijo extends Model
{
    protected $table = 'clientes_ipv6_prefijos';

    protected $fillable = [
        'client_id',
        'bloque_id',
        'router_id',
        'segmento_id',
        'usuario_pppoe',
        'prefijo',
        'duid',
        'asignado_en',
        'liberado_en',
        'asignado_por',
        'estado',
    ];

    protected $casts = [
        'asignado_en' => 'datetime',
        'liberado_en' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function bloque()
    {
        return $this->belongsTo(Ipv6Bloque::class, 'bloque_id');
    }

    public function router()
    {
        return $this->belongsTo(Ipv6Router::class, 'router_id');
    }

    public function segmento()
    {
        return $this->belongsTo(Ipv6PlanSegmento::class, 'segmento_id');
    }

    public function asignadoPor()
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('estado', 'activo');
    }

    public function scopeLiberados(Builder $q): Builder
    {
        return $q->where('estado', 'liberado');
    }
}
