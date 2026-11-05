<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/** UNI-T M2a: meta versionada por concepto. Informativa mientras afecta_pago=false. */
class TalentoMeta extends BaseModel
{
    use SoftDeletes;

    protected $table = 'talento_metas';

    protected $fillable = [
        'concepto', 'alcance_tipo', 'alcance_clave', 'periodo', 'valor', 'unidad', 'afecta_pago',
        'version', 'reemplaza_id', 'vigente_desde', 'vigente_hasta', 'origen', 'origen_id', 'motivo',
    ];

    protected $casts = [
        'valor'         => 'decimal:2',
        'afecta_pago'   => 'boolean',
        'version'       => 'integer',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    public function reemplaza()
    {
        return $this->belongsTo(self::class, 'reemplaza_id');
    }
}
