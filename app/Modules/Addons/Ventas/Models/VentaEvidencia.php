<?php

namespace App\Modules\Addons\Ventas\Models;

use App\Models\BaseModel;
use App\Modules\Addons\Talento\Models\TalentoColaborador;

/**
 * Evidencia de trabajo sobre una custodia (item #9990780, tabla creada en #9990799).
 * Append-only: sin updated_at (la evidencia no se edita una vez subida).
 */
class VentaEvidencia extends BaseModel
{
    const UPDATED_AT = null;

    protected $table = 'ventas_evidencias';

    protected $fillable = [
        'custodia_id',
        'colaborador_id',
        'tipo',
        'descripcion',
        'archivo_path',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function custodia()
    {
        return $this->belongsTo(VentaCustodia::class, 'custodia_id');
    }

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }
}
