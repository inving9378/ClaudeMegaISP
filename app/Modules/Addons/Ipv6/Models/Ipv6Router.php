<?php

namespace App\Modules\Addons\Ipv6\Models;

use Illuminate\Database\Eloquent\Model;

// Model plano (NO BaseModel): ipv6_routers no tiene created_by/updated_by.
class Ipv6Router extends Model
{
    protected $table = 'ipv6_routers';

    protected $fillable = [
        'nombre',
        'host_api',
        'puerto_api',
        'version_routeros',
        'board_name',
        'driver_familia',
        'version_manual',
        'ultimo_contacto',
        'estado',
    ];

    protected $casts = [
        'version_manual'  => 'boolean',
        'ultimo_contacto' => 'datetime',
    ];

    public function planSegmentos()
    {
        return $this->hasMany(Ipv6PlanSegmento::class, 'router_id');
    }

    public function clientePrefijos()
    {
        return $this->hasMany(ClienteIpv6Prefijo::class, 'router_id');
    }

    public function despliegues()
    {
        return $this->hasMany(Ipv6Despliegue::class, 'router_id');
    }
}
