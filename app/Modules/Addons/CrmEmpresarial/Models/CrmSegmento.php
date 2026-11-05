<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Model;

class CrmSegmento extends Model
{
    protected $table = 'crm_segmentos';
    protected $guarded = ['id'];
    protected $casts = ['requiere_expediente' => 'boolean', 'activo' => 'boolean'];

    public function expedientes()
    {
        return $this->hasMany(CrmExpediente::class, 'segmento_id');
    }
}
