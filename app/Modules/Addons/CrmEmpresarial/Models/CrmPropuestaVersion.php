<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Model;

class CrmPropuestaVersion extends Model
{
    protected $table = 'crm_propuesta_versiones';
    protected $guarded = ['id'];
    protected $casts = ['snapshot' => 'array', 'vigencia_hasta' => 'date', 'aprobada_at' => 'datetime'];

    public function propuesta() { return $this->belongsTo(CrmPropuesta::class, 'propuesta_id'); }
    public function historial() { return $this->hasMany(CrmPropuestaHistorial::class, 'propuesta_version_id')->orderBy('id'); }
}
