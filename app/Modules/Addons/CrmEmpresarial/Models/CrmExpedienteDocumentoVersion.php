<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Model;

/** Versión INMUTABLE de un documento (append-only, sin updated_at). */
class CrmExpedienteDocumentoVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'crm_expediente_documento_versiones';
    protected $guarded = ['id'];

    public function documento() { return $this->belongsTo(CrmExpedienteDocumento::class, 'documento_id'); }
}
