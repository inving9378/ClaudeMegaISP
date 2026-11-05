<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmExpedienteContacto extends Model
{
    use SoftDeletes;

    protected $table = 'crm_expediente_contactos';
    protected $guarded = ['id'];

    public function expediente() { return $this->belongsTo(CrmExpediente::class, 'expediente_id'); }
}
