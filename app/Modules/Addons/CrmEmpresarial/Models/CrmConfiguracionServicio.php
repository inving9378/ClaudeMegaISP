<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmConfiguracionServicio extends Model
{
    use SoftDeletes;

    protected $table = 'crm_configuraciones_servicio';
    protected $guarded = ['id'];
    protected $casts = [
        'simetrico' => 'boolean', 'bloque_adicional' => 'boolean', 'ipv6' => 'boolean',
        'red_proteccion_logica' => 'boolean', 'red_diversidad_equipos' => 'boolean',
        'red_diversidad_proveedores' => 'boolean', 'red_verificada' => 'boolean',
        'aprobado_at' => 'datetime',
    ];

    public function expediente() { return $this->belongsTo(CrmExpediente::class, 'expediente_id'); }

    public function sitio() { return $this->belongsTo(CrmExpedienteSitio::class, 'sitio_id'); }
}
