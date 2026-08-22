<?php

namespace App\Modules\Addons\Ipv6\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

// Model plano (NO BaseModel): ipv6_despliegues no tiene created_by/updated_by.
class Ipv6Despliegue extends Model
{
    protected $table = 'ipv6_despliegues';

    protected $fillable = [
        'bloque_id',
        'router_id',
        'tipo_operacion',
        'comandos_generados',
        'resultado',
        'respuesta_router',
        'usuario_id',
        'ejecutado_en',
    ];

    protected $casts = [
        'ejecutado_en' => 'datetime',
    ];

    public function bloque()
    {
        return $this->belongsTo(Ipv6Bloque::class, 'bloque_id');
    }

    public function router()
    {
        return $this->belongsTo(Ipv6Router::class, 'router_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
