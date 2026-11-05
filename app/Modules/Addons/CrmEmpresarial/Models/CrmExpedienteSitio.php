<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmExpedienteSitio extends Model
{
    use SoftDeletes;

    protected $table = 'crm_expediente_sitios';
    protected $guarded = ['id'];
    protected $casts = ['lat' => 'float', 'lng' => 'float'];

    public function expediente() { return $this->belongsTo(CrmExpediente::class, 'expediente_id'); }

    public function contacto() { return $this->belongsTo(CrmExpedienteContacto::class, 'contacto_id'); }
}
