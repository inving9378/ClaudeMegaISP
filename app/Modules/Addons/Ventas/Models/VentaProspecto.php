<?php

namespace App\Modules\Addons\Ventas\Models;

use App\Models\BaseModel;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fuente única de prospectos del motor de ventas (tabla creada en #9990799).
 * `colaborador_id` NULL = prospecto en el pool general, sin asignar.
 */
class VentaProspecto extends BaseModel
{
    use SoftDeletes;

    protected $table = 'ventas_prospectos';

    protected $fillable = [
        'colaborador_id',
        'client_id',
        'nombre',
        'telefono',
        'email',
        'origen',
        'origen_id',
        'estado',
        'notas',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function custodias()
    {
        return $this->hasMany(VentaCustodia::class, 'prospecto_id');
    }
}
