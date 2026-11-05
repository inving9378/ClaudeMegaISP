<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmExpedienteRepresentante extends Model
{
    use SoftDeletes;

    protected $table = 'crm_expediente_representantes';
    protected $guarded = ['id'];
    protected $casts = ['instrumento_fecha' => 'date', 'vigencia_facultades' => 'date', 'validado_at' => 'datetime'];

    public function expediente() { return $this->belongsTo(CrmExpediente::class, 'expediente_id'); }
}
