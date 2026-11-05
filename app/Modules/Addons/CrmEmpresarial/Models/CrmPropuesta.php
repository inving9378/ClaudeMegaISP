<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Model;

class CrmPropuesta extends Model
{
    protected $table = 'crm_propuestas';
    protected $guarded = ['id'];

    public function expediente() { return $this->belongsTo(CrmExpediente::class, 'expediente_id'); }
    public function versiones() { return $this->hasMany(CrmPropuestaVersion::class, 'propuesta_id')->orderBy('version'); }

    public function vigente(): ?CrmPropuestaVersion
    {
        return $this->versiones()->where('version', $this->version_actual)->first();
    }
}
