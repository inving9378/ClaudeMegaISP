<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmExpediente extends Model
{
    use SoftDeletes;

    protected $table = 'crm_expedientes';
    protected $guarded = ['id'];
    protected $casts = ['requiere_expediente_dedicado' => 'boolean', 'fecha_constitucion' => 'date'];

    public function segmento() { return $this->belongsTo(CrmSegmento::class, 'segmento_id'); }
    public function representantes() { return $this->hasMany(CrmExpedienteRepresentante::class, 'expediente_id'); }
    public function contactos() { return $this->hasMany(CrmExpedienteContacto::class, 'expediente_id'); }
    public function sitios() { return $this->hasMany(CrmExpedienteSitio::class, 'expediente_id'); }
    public function documentos() { return $this->hasMany(CrmExpedienteDocumento::class, 'expediente_id'); }
}
