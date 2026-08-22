<?php

namespace App\Modules\Addons\Ipv6\Models;

use Illuminate\Database\Eloquent\Model;

// Model plano (NO BaseModel): ipv6_bloques no tiene created_by/updated_by.
class Ipv6Bloque extends Model
{
    protected $table = 'ipv6_bloques';

    protected $fillable = [
        'prefijo',
        'proveedor',
        'referencia_contrato',
        'ip_transito',
        'gateway_proveedor',
        'estado',
        'activado_en',
        'deprecado_en',
        'retirado_en',
    ];

    protected $casts = [
        'activado_en'  => 'datetime',
        'deprecado_en' => 'datetime',
        'retirado_en'  => 'datetime',
    ];

    public function planSegmentos()
    {
        return $this->hasMany(Ipv6PlanSegmento::class, 'bloque_id');
    }
}
