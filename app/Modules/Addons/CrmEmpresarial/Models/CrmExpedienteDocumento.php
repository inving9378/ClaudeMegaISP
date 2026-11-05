<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmExpedienteDocumento extends Model
{
    use SoftDeletes;

    protected $table = 'crm_expediente_documentos';
    protected $guarded = ['id'];
    protected $casts = ['vigencia_fin' => 'date'];

    public function expediente() { return $this->belongsTo(CrmExpediente::class, 'expediente_id'); }

    public function versiones() { return $this->hasMany(CrmExpedienteDocumentoVersion::class, 'documento_id'); }
}
